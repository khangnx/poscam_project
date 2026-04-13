<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VoucherController extends Controller
{
    /**
     * Kiểm tra KH có đủ điều kiện chơi nhận Voucher không.
     * Game engine chỉ truyền sđt.
     */
    public function checkEligibility(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Vui lòng cung cấp số điện thoại.']);
        }

        $customer = Customer::with('group')
            ->where('phone', $request->phone)
            ->first();

        if (!$customer) {
            return response()->json(['success' => false, 'message' => 'Khách hàng không tồn tại.']);
        }

        // Kiểm tra nhóm (hạng Bạc có min_points > 0 hoặc không phải Khách thường)
        $isGroupValid = $customer->group && $customer->group->min_points > 0;

        if (!$isGroupValid) {
            return response()->json(['success' => false, 'message' => 'Rất tiếc, bạn cần tối thiểu hạng Bạc để tham gia. Khách ưu đãi hiện tại của bạn chưa đủ điều kiện.']);
        }

        // Kiểm tra xem khách có voucher nào chưa dùng và chưa hết hạn không (chỉ tối đa 1)
        $existingVoucher = Voucher::where('customer_id', $customer->id)->valid()->first();
        if ($existingVoucher) {
            return response()->json(['success' => false, 'message' => 'Bạn đã có một Voucher chưa sử dụng, vui lòng sử dụng trước khi tham gia game tiếp nhé!']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Đủ điều kiện tham gia.',
            'tenant_id' => $customer->tenant_id,
            'customer_name' => $customer->name,
        ]);
    }

    /**
     * Phát hành Voucher, nội bộ từ worker/game engine gọi vào.
     * Vì game chạy nội bộ (worker), nên giả định truyền qua kèm một header X-Game-Secret.
     */
    public function issue(Request $request)
    {
        // Có thể bổ sung check env token (đơn giản hoá vì game chạy chung docker/LAN)
        $secret = $request->header('X-Internal-Secret');
        if ($secret !== 'worker-secret-token') {
            return response()->json(['success' => false, 'message' => 'Unauthorized token'], 403);
        }

        \Illuminate\Support\Facades\Log::info('Voucher issuance request received. Phone: ' . $request->phone . ', ID: ' . $request->customer_id);

        $customer = null;
        if ($request->customer_id) {
            $customer = Customer::find($request->customer_id);
        }

        if (!$customer && $request->phone) {
            $customer = Customer::where('phone', $request->phone)->first();
        }

        if (!$customer) {
            \Illuminate\Support\Facades\Log::warning('Customer not found for identifiers provided.');
            return response()->json(['success' => false, 'message' => 'Khách không tồn tại'], 404);
        }

        \Illuminate\Support\Facades\Log::info('Customer found: ' . $customer->name . ' (ID: ' . $customer->id . ')');

        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            // Tạo Voucher 3% hạn 7 ngày
            $voucher = Voucher::create([
                'tenant_id' => $customer->tenant_id,
                'customer_id' => $customer->id,
                'discount_rate' => 3.0,
                'is_used' => false,
                'expiry_date' => now()->addDays(7),
            ]);

            \Illuminate\Support\Facades\DB::commit();
            \Illuminate\Support\Facades\Log::info("Voucher Saved Successfully for Customer ID: {$customer->id}");
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            \Illuminate\Support\Facades\Log::error("Voucher Save Failed: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Lỗi lưu voucher: ' . $e->getMessage()], 500);
        }

        // Polling will handle notification in POS

        return response()->json([
            'success' => true,
            'message' => 'Voucher đã được cấp thành công',
            'voucher' => $voucher
        ]);
    }

    /**
     * Trả về Voucher chưa dùng (chỉ 1) của Customer cho POS hiển thị
     */
    public function getForCustomer($customerId)
    {
        $voucher = Voucher::where('customer_id', $customerId)
            ->valid()
            ->first();

        return response()->json([
            'success' => true,
            'data' => $voucher // có thể null
        ]);
    }
}
