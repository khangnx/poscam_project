<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\OrderStatusLog;
use App\Services\PayOSService;
use App\Services\FastAPIService;
use App\Services\TelegramService;
use App\Events\PaymentReceived;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class WebhookController extends Controller
{
    protected PayOSService $payOSService;
    protected FastAPIService $fastAPIService;

    public function __construct(PayOSService $payOSService, FastAPIService $fastAPIService)
    {
        $this->payOSService = $payOSService;
        $this->fastAPIService = $fastAPIService;
    }

    /**
     * Handle PayOS Webhook.
     */
    public function handlePayOS(Request $request)
    {
        $payload = $request->all();
        
        Log::info('PayOS Webhook Received', $payload);

        // 1. Check Signature
        if (!$this->payOSService->verifyWebhookData($payload['data'] ?? $payload)) {
            Log::warning('PayOS Webhook Invalid Signature');
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 400);
        }

        $data = $payload['data'] ?? $payload;
        $description = $data['description'] ?? '';
        $amount = $data['amount'] ?? 0;
        $referenceId = $data['reference'] ?? '';

        // 2. Parse Order ID from description: SHOPPAY [ID]
        preg_match('/SHOPPAY\s+(\d+)/i', $description, $matches);
        if (empty($matches)) {
            Log::warning('PayOS Webhook: Order ID not found in description: ' . $description);
            return response()->json(['success' => false, 'message' => 'Order ID not found'], 200);
        }

        $orderId = $matches[1];
        $order = Order::with('items.product')->find($orderId);

        if (!$order) {
            Log::warning('PayOS Webhook: Order not found: ' . $orderId);
            return response()->json(['success' => false, 'message' => 'Order not found'], 200);
        }

        // 3. Validation
        if ($order->status === 'paid' || $order->status === 'completed') {
            return response()->json(['success' => true, 'message' => 'Order already processed'], 200);
        }

        if ($amount < $order->total_amount) {
            Log::warning("PayOS Webhook: Insufficient amount for Order #{$orderId}. Paid: {$amount}, Required: {$order->total_amount}");
            
            // Record partial payment or failed status
            Payment::create([
                'order_id' => $order->id,
                'amount' => $amount,
                'bank_transaction_id' => $referenceId,
                'status' => 'partial',
                'description' => $description
            ]);

            return response()->json(['success' => false, 'message' => 'Insufficient amount'], 200);
        }

        // 4. Update Order & Record Payment
        try {
            DB::beginTransaction();

            $oldStatus = $order->status;
            $order->update([
                'status' => 'paid',
                'payment_method' => 'transfer'
            ]);

            OrderStatusLog::create([
                'order_id' => $order->id,
                'user_id' => null, // System/Webhook
                'from_status' => $oldStatus,
                'to_status' => 'paid',
            ]);

            Payment::create([
                'order_id' => $order->id,
                'amount' => $amount,
                'bank_transaction_id' => $referenceId,
                'status' => 'success',
                'description' => $description
            ]);

            DB::commit();

            // 5. Broadcast Success
            try {
                broadcast(new PaymentReceived($order));
            } catch (\Throwable $be) {
                Log::warning('PayOS Webhook Broadcast failed: ' . $be->getMessage());
            }

            // 6. Trigger Post-Order Actions (Telegram & Print)
            try {
                $this->triggerPostOrderActions($order);
            } catch (\Throwable $ae) {
                Log::error('PayOS Webhook Post-Order Actions failed: ' . $ae->getMessage());
            }

            return response()->json(['success' => true, 'message' => 'Payment processed successfully'], 200);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('PayOS Webhook Processing Error: ' . $e->getMessage(), [
                'stack' => $e->getTraceAsString()
            ]);
            return response()->json(['success' => false, 'message' => 'Internal Server Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Simulate Payment via Dev UI.
     */
    public function simulatePayment(Request $request)
    {
        $request->validate([
            'order_id' => 'required|integer',
            'amount' => 'required|numeric'
        ]);

        $order = Order::with('items.product')->find($request->order_id);

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        if ($order->status === 'paid' || $order->status === 'completed') {
            return response()->json(['success' => true, 'message' => 'Order already processed']);
        }

        try {
            DB::beginTransaction();

            $oldStatus = $order->status;
            $order->update([
                'status' => 'paid',
                'payment_method' => 'transfer'
            ]);

            OrderStatusLog::create([
                'order_id' => $order->id,
                'user_id' => $request->user()?->id ?? null,
                'from_status' => $oldStatus,
                'to_status' => 'paid',
            ]);

            Payment::create([
                'order_id' => $order->id,
                'amount' => $request->amount,
                'bank_transaction_id' => 'SIMULATE_' . time(),
                'status' => 'success',
                'description' => 'Simulate Payment via Dev UI'
            ]);

            DB::commit();

            // Broadcast success
            try {
                broadcast(new PaymentReceived($order));
            } catch (\Throwable $be) {
                Log::warning('Simulate Payment Broadcast failed: ' . $be->getMessage());
            }

            return response()->json(['success' => true, 'message' => 'Simulated successfully']);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Simulate Payment Error: ' . $e->getMessage(), [
                'stack' => $e->getTraceAsString()
            ]);
            return response()->json(['success' => false, 'message' => 'Internal Server Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Test Success Route: Manually trigger success flow for an order.
     */
    public function testSuccess($id)
    {
        $order = Order::with('items.product')->find($id);

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        // 1. Update status
        $order->update(['status' => 'paid', 'payment_method' => 'transfer']);

        // 2. Broadcast
        broadcast(new PaymentReceived($order));

        // 3. Trigger Actions
        $this->triggerPostOrderActions($order);

        return response()->json([
            'success' => true, 
            'message' => 'Test success triggered',
            'order_id' => $id
        ]);
    }

    /**
     * Helper to trigger FastAPI print and Telegram notification
     * (Duplicated from OrderController or moved to a shared trait/service in a real app)
     */
    private function triggerPostOrderActions(Order $order)
    {
        // 1. Send Telegram Notification
        try {
            $telegram = app(TelegramService::class);
            $message  = "✅ *Thanh toán tự động thành công!*\n\n";
            $message .= "Mã đơn: #{$order->id}\n";
            $message .= "Khách hàng: " . ($order->customer_name ?? "Khách lẻ") . "\n";
            $message .= "Tổng tiền: " . number_format($order->total_amount, 0, ',', '.') . " VND\n";
            $message .= "Phương thức: Chuyển khoản (VietQR)\n";
            $telegram->sendMessage($message);
        } catch (\Exception $e) {
            Log::error('Telegram error on webhook: ' . $e->getMessage());
        }

        // 2. Print Receipt
        try {
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
                'total' => $order->total_amount,
                'payment_method' => 'Chuyển khoản (VietQR)'
            ];
            
            $this->fastAPIService->sendPrintJob($printPayload);
        } catch (\Exception $e) {
            Log::error('FastAPI print error on webhook: ' . $e->getMessage());
        }
    }
}
