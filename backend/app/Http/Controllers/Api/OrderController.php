<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Services\TelegramService;
use App\Services\FastAPIService;
use App\Services\LoyaltyService;
use App\Models\OrderStatusLog;
use App\Models\Customer;
use App\Models\Voucher;

class OrderController extends Controller
{
    protected InventoryService $inventoryService;
    protected LoyaltyService $loyaltyService;
    protected \App\Services\PayOSService $payOSService;

    public function __construct(InventoryService $inventoryService, LoyaltyService $loyaltyService, \App\Services\PayOSService $payOSService)
    {
        $this->inventoryService = $inventoryService;
        $this->loyaltyService = $loyaltyService;
        $this->payOSService = $payOSService;
    }

    /**
     * Display a listing of orders.
     */
    public function index()
    {
        // Load items.product
        $orders = Order::with('items.product')->latest()->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Orders retrieved successfully',
            'data'    => $orders
        ]);
    }

    /**
     * Store a newly created order in storage.
     * Trừ kho + ghi log biến động nằm trong cùng 1 DB Transaction.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_name'          => 'nullable|string|max:255',
            'customer_id'            => 'nullable|exists:customers,id',
            'items'                  => 'required|array|min:1',
            'items.*.product_id'     => 'required|exists:products,id',
            'items.*.quantity'       => 'required|integer|min:1',
            'status'                 => 'nullable|in:pending,completed,cancelled',
            'payment_method'         => 'nullable|string|max:255',
            'voucher_id'             => 'nullable|exists:vouchers,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $totalAmount = 0;
            $tenantId    = auth()->user()->tenant_id;
            $userId      = auth()->id();

            // --------------------------------------------------------
            // Bước 1: Validate sản phẩm, kiểm tra tồn kho, tính tổng
            // --------------------------------------------------------
            $orderItemsData = [];
            $stockSnapshots = []; // Lưu snapshot oldStock trước khi trừ

            foreach ($request->items as $item) {
                $product = Product::where('id', $item['product_id'])
                                  ->where('tenant_id', $tenantId)
                                  ->lockForUpdate()
                                  ->first();

                // --------------------------------------------------------
                // Bước 1.1: Trừ tồn kho (Sử dụng InventoryService để xử lý Định mức)
                // --------------------------------------------------------
                $this->inventoryService->deductIngredients($product, $item['quantity'], null);

                $priceAtPurchase = $product->selling_price;
                $costAtPurchase  = $product->cost_price;
                $totalAmount    += $priceAtPurchase * $item['quantity'];

                $orderItemsData[] = [
                    'product'   => $product,
                    'quantity'  => $item['quantity'],
                    'price_at_purchase' => $priceAtPurchase,
                    'cost_at_purchase'  => $costAtPurchase,
                ];
            }

            // --------------------------------------------------------
            // Bước 2: Tìm ca làm việc đang hoạt động
            // --------------------------------------------------------
            $user    = auth()->user();
            $shiftId = null;

            if ($user->hasRole('staff')) {
                $ownShift = \App\Models\Shift::where('user_id', $user->id)
                    ->whereNull('end_time')
                    ->first();
                $shiftId = $ownShift?->id;
            } else {
                $anyActiveShift = \App\Models\Shift::whereNull('end_time')
                    ->latest()
                    ->first();
                $shiftId = $anyActiveShift?->id;
            }

            // --------------------------------------------------------
            // Bước 2.5: Xử lý chiết khấu nhóm khách hàng
            // --------------------------------------------------------
            $customer = null;
            $discountAmount = 0;
            if ($request->customer_id) {
                $customer = Customer::with('group')->find($request->customer_id);
                if ($customer) {
                    $discountPercent = $this->loyaltyService->getDiscount($customer);
                    if ($discountPercent > 0) {
                        $discountAmount = ($totalAmount * $discountPercent) / 100;
                    }
                }
            }

            // --------------------------------------------------------
            // Bước 2.6: Trừ Voucher Game nếu có
            // --------------------------------------------------------
            $appliedVoucher = null;
            if ($request->voucher_id) {
                $voucher = Voucher::where('id', $request->voucher_id)
                    ->where('customer_id', $request->customer_id)
                    ->valid()
                    ->lockForUpdate()
                    ->first();
                    
                if ($voucher) {
                    $appliedVoucher = $voucher;
                    // Mặc định tính thẻ bạc trước rồi mới giảm 3% tiếp, 
                    // hoặc nếu muốn dựa trên Total Amount ban đầu:
                    $voucherDiscount = ($totalAmount * $voucher->discount_rate) / 100;
                    $discountAmount += $voucherDiscount;
                }
            }

            // --------------------------------------------------------
            // Bước 3: Tạo đơn hàng
            // --------------------------------------------------------
            $orderStatus = $request->status ?? 'paid';
            
            // Nếu phương thức là chuyển khoản, để trạng thái là pending chờ Webhook
            if ($request->payment_method === 'transfer') {
                $orderStatus = 'pending';
            }
            $finalAmount = $totalAmount - $discountAmount;

            $order = Order::create([
                'tenant_id'      => $tenantId,
                'user_id'        => $userId,
                'shift_id'       => $shiftId,
                'customer_id'    => $request->customer_id,
                'customer_name'  => $customer ? $customer->name : $request->customer_name,
                'total_amount'   => $finalAmount,
                'discount_amount' => $discountAmount,
                'status'         => $orderStatus,
                'payment_method' => $request->payment_method ?? 'cash',
            ]);

            // Log initial status
            OrderStatusLog::create([
                'order_id' => $order->id,
                'user_id' => $userId,
                'from_status' => null,
                'to_status' => $orderStatus,
            ]);

            // --------------------------------------------------------
            // Bước 4: Tạo Order Items + Ghi log biến động (nếu completed)
            // --------------------------------------------------------
            foreach ($orderItemsData as $itemData) {
                $order->items()->create([
                    'product_id'        => $itemData['product']->id,
                    'quantity'          => $itemData['quantity'],
                    'price_at_purchase' => $itemData['price_at_purchase'],
                    'cost_at_purchase'  => $itemData['cost_at_purchase'],
                ]);

                // Log biến động kho đã được xử lý tự động trong deductIngredients khi gọi handleStockChange
            }

            // --------------------------------------------------------
            // Bước 5: Tích điểm (nếu paid/completed) & Cập nhật Voucher
            // --------------------------------------------------------
            if (($order->status === 'paid' || $order->status === 'completed') && $order->customer_id) {
                $this->loyaltyService->applyLoyalty($order);
                
                // Đánh dấu đã dùng Voucher
                if ($appliedVoucher) {
                    $appliedVoucher->update(['is_used' => true]);
                    // Tạm lưu thông tin voucher vào order instance để tí in bill
                    $order->used_voucher_rate = $appliedVoucher->discount_rate;
                }
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Order creation failed',
                'error'   => $e->getMessage()
            ], 400);
        }

        // Post-Order Actions (KHÔNG rollback transaction chính nếu lỗi)
        try {
            $orderWithRelations = $order->load('items.product');

            if ($order->status === 'paid' || $order->status === 'completed') {
                $this->triggerPostOrderActions($orderWithRelations);
            }
        } catch (\Throwable $e) {
            try { Log::error('Integration trigger failed: ' . $e->getMessage()); } catch (\Throwable $err) {}
        }

        return response()->json([
            'success' => true,
            'message' => 'Order created successfully',
            'data'    => $order->load('items.product'),
            'requires_payment' => $order->payment_method === 'transfer' && $order->status === 'pending'
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Order $order)
    {
        return response()->json([
            'success' => true,
            'data'    => $order->load('items.product')
        ]);
    }

    /**
     * Update the specified order in storage.
     * Xử lý đảo ngược: Khi hủy đơn → cộng trả kho + ghi log "Hoàn hàng".
     */
    public function update(Request $request, Order $order)
    {
        $validator = Validator::make($request->all(), [
            'status'         => 'nullable|in:pending,completed,cancelled',
            'payment_method' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors()
            ], 422);
        }

        $previousStatus = $order->status;

        try {
            DB::beginTransaction();

            $order->update($request->only(['status', 'payment_method']));

            // Khi đơn bị hủy từ trạng thái paid/completed → hoàn trả kho + ghi log
            if ($request->status === 'cancelled' && in_array($previousStatus, ['paid', 'completed'])) {
                $this->reverseStockForOrder($order, "Hoàn hàng - Đơn #{$order->id} bị hủy");
            }

            // Khi đơn được chuyển sang paid từ pending → ghi log bán hàng + tích điểm
            if ($request->status === 'paid' && $previousStatus === 'pending') {
                $this->logSaleForOrder($order);

                OrderStatusLog::create([
                    'order_id' => $order->id,
                    'user_id' => auth()->id(),
                    'from_status' => $previousStatus,
                    'to_status' => 'paid',
                ]);

                if ($order->customer_id) {
                    $this->loyaltyService->applyLoyalty($order);
                }
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Order update failed: ' . $e->getMessage()
            ], 400);
        }

        // Nếu status vừa chuyển thành paid → trigger integrations
        if ($request->status === 'paid' && $previousStatus === 'pending') {
            try {
                $this->triggerPostOrderActions($order->load('items.product'));
            } catch (\Throwable $e) {
                try { Log::error('Integration trigger failed: ' . $e->getMessage()); } catch (\Throwable $err) {}
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Order updated successfully',
            'data'    => $order->load('items.product')
        ]);
    }

    /**
     * Remove the specified order from storage.
     * Nếu đơn đã completed → hoàn trả kho + ghi log trước khi xóa (Soft Delete).
     */
    public function destroy(Order $order)
    {
        try {
            DB::beginTransaction();

            if ($order->status === 'paid' || $order->status === 'completed') {
                $this->reverseStockForOrder($order, "Hoàn hàng - Đơn #{$order->id} bị xóa");
            }

            $order->delete(); // Soft Delete

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Order delete failed: ' . $e->getMessage()
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Order deleted successfully'
        ]);
    }

    /**
     * Get revenue stats for dashboard.
     */
    public function stats()
    {
        $totalRevenue    = Order::where('status', 'completed')->sum('total_amount');
        $totalOrders     = Order::count();
        $completedOrders = Order::where('status', 'completed')->count();

        return response()->json([
            'success' => true,
            'message' => 'Order stats retrieved successfully',
            'data'    => [
                'total_revenue'    => $totalRevenue,
                'total_orders'     => $totalOrders,
                'completed_orders' => $completedOrders
            ]
        ]);
    }

    /**
     * Cộng trả kho cho tất cả items trong đơn hàng và ghi log "Hoàn hàng".
     * Phải được gọi bên trong một DB::transaction đang hoạt động.
     */
    private function reverseStockForOrder(Order $order, string $note): void
    {
        $order->loadMissing('items.product');
        $userId   = auth()->id();
        $tenantId = $order->tenant_id;

        foreach ($order->items as $item) {
            $product = $item->product; // Đã loadItems.product
            if (!$product) continue;

            // Hoàn lại kho nguyên liệu theo định mức
            foreach ($product->recipes as $recipe) {
                $returnedQty = $recipe->quantity * $item->quantity;
                $this->inventoryService->handleStockChange(
                    $recipe->material_id,
                    $returnedQty,
                    'return',
                    $note . " ({$product->name})",
                    $userId,
                    $order->id
                );
            }
        }
    }

    /**
     * Ghi log bán hàng cho đơn hàng khi được chuyển sang completed từ pending.
     * Tồn kho đã bị trừ ở lúc tạo đơn, chỉ cần tạo bản ghi log.
     */
    private function logSaleForOrder(Order $order): void
    {
        $order->loadMissing('items.product');
        $userId   = auth()->id();
        $tenantId = $order->tenant_id;

        foreach ($order->items as $item) {
            $product = $item->product;
            if (!$product) continue;

            foreach ($product->recipes as $recipe) {
                $usedQty = $recipe->quantity * $item->quantity;
                $this->inventoryService->handleStockChange(
                    $recipe->material_id,
                    -$usedQty,
                    'sale',
                    "Xuất kho để chế biến {$product->name} - Đơn hàng #{$order->id}",
                    $userId,
                    $order->id
                );
            }
        }
    }

    /**
     * Generate PayOS payment link for an order.
     */
    public function generatePaymentLink($id)
    {
        $order = Order::find($id);
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        $result = $this->payOSService->createPaymentLink($order);
        if ($result && isset($result['data'])) {
            return response()->json([
                'success' => true,
                'data' => $result['data']
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to generate payment link'
        ], 400);
    }

    /**
     * Actively check payment status from PayOS.
     */
    public function checkPaymentStatus($id)
    {
        $order = Order::with('items.product')->find($id);
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        // If already paid/completed, return success
        if ($order->status === 'paid' || $order->status === 'completed') {
            return response()->json(['success' => true, 'status' => 'PAID', 'message' => 'Order already processed']);
        }

        $result = $this->payOSService->getPaymentStatus($order->id);
        
        if ($result && isset($result['data'])) {
            $status = $result['data']['status']; // PAID, PENDING, CANCELLED
            
            if ($status === 'PAID') {
                // Manually trigger success flow
                DB::transaction(function() use ($order) {
                    $order->update(['status' => 'paid']);
                    // Record payment if not exists
                    \App\Models\Payment::firstOrCreate(
                        ['bank_transaction_id' => $result['data']['transactions'][0]['reference'] ?? 'MANUAL_' . time()],
                        [
                            'order_id' => $order->id,
                            'amount' => $order->total_amount,
                            'status' => 'success',
                            'description' => 'Manual status check'
                        ]
                    );
                });

                // Broadcast
                broadcast(new \App\Events\PaymentReceived($order));
                
                // Trigger printing
                $this->triggerPostOrderActions($order);

                return response()->json(['success' => true, 'status' => 'PAID', 'message' => 'Payment confirmed']);
            }

            return response()->json(['success' => true, 'status' => $status, 'message' => 'Payment status: ' . $status]);
        }

        return response()->json(['success' => false, 'message' => 'Could not fetch status from PayOS'], 400);
    }

    /**
     * Helper to trigger FastAPI print and Telegram notification
     */
    private function triggerPostOrderActions(Order $order)
    {
        // 1. Send Telegram Notification
        try {
            $telegram = app(TelegramService::class);
            $message  = "🎉 *Có Đơn Hàng Mới!*\n\n";
            $message .= "Mã đơn: #{$order->id}\n";
            $message .= "Khách hàng: " . ($order->customer_name ?? "Khách lẻ") . "\n";
            $message .= "Tổng tiền: " . number_format($order->total_amount, 0, ',', '.') . " VND\n";
            $telegram->sendMessage($message);
        } catch (\Exception $e) {
            Log::error('Telegram error on order: ' . $e->getMessage());
        }

        // 2. Print Receipt
        try {
            $fastAPI = app(FastAPIService::class);
            $printPayload = [
                'shop_name' => 'PosCam Shop',
                'address'   => '123 Fake Street, HCM City',
                'greeting'  => 'Cảm ơn quý khách và hẹn gặp lại!',
                'items'     => $order->items->map(function ($item) {
                    return [
                        'name'     => $item->product->name,
                        'quantity' => $item->quantity,
                        'price'    => $item->price_at_purchase
                    ];
                })->toArray(),
                'total' => $order->total_amount
            ];
            
            if (isset($order->used_voucher_rate)) {
                $printPayload['note'] = "Ưu đãi: Giảm {$order->used_voucher_rate}% từ Game Đua Xe";
            }
            
            $fastAPI->sendPrintJob($printPayload);
        } catch (\Exception $e) {
            Log::error('FastAPI print error on order: ' . $e->getMessage());
        }
    }
}
