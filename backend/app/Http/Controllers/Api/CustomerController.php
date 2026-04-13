<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\LoyaltyLog;
use App\Services\LoyaltyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Customer::with('group');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $customers = $query->latest()->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data'    => $customers
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'phone'    => 'required|string|max:20|unique:customers,phone,NULL,id,tenant_id,' . $tenantId,
            'group_id' => 'nullable|exists:customer_groups,id',
        ], [
            'phone.unique' => 'Số điện thoại này đã được sử dụng cho một khách hàng khác.'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $groupId = $request->group_id;

        // Logic for POS default group
        if (empty($groupId) && $request->source === 'pos') {
            $defaultGroup = CustomerGroup::where('name', 'Khách thường')->first();

            if (!$defaultGroup) {
                $defaultGroup = CustomerGroup::create([
                    'name' => 'Khách thường',
                    'earning_rate' => 1,
                    'discount_percent' => 0
                ]);
            }
            $groupId = $defaultGroup->id;
        }

        $customer = Customer::create([
            'tenant_id' => $tenantId,
            'name'      => $request->name,
            'phone'     => $request->phone,
            'group_id'  => $groupId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Khách hàng đã được tạo',
            'data'    => $customer->load('group')
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Customer $customer)
    {
        return response()->json([
            'success' => true,
            'data'    => $customer->load(['group', 'orders.items.product', 'loyaltyLogs' => function($q) {
                $q->latest()->limit(20);
            }])
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Customer $customer)
    {
        $tenantId = auth()->user()->tenant_id;

        $validator = Validator::make($request->all(), [
            'name'        => 'nullable|string|max:255',
            'phone'       => 'nullable|string|max:20|unique:customers,phone,' . $customer->id . ',id,tenant_id,' . $tenantId,
            'group_id'    => 'nullable|exists:customer_groups,id',
            'points'      => 'nullable|integer|min:0',
            'total_spent' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $oldPoints = $customer->points;
        $newPoints = $request->has('points') ? (int) $request->points : $oldPoints;

        $customer->update($request->only(['name', 'phone', 'group_id', 'points', 'total_spent']));

        // Log manual points adjustment if changed
        if ($oldPoints !== $newPoints) {
            LoyaltyLog::create([
                'customer_id'    => $customer->id,
                'points_changed' => abs($newPoints - $oldPoints),
                'type'           => $newPoints > $oldPoints ? 'plus' : 'minus',
                'balance'        => $customer->points,
                'description'    => 'Điều chỉnh điểm thủ công từ Quản trị viên',
            ]);
        }

        // Trigger rank-up check
        $loyaltyService = app(LoyaltyService::class);
        $loyaltyService->checkAndUpgradeGroup($customer->fresh(['group']));

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật thành công',
            'data'    => $customer->load('group')
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Customer $customer)
    {
        $customer->delete();
        return response()->json([
            'success' => true,
            'message' => 'Khách hàng đã được xóa'
        ]);
    }

    /**
     * Search customer by phone for POS.
     */
    public function search(Request $request)
    {
        $phone = $request->phone;
        if (empty($phone)) {
            return response()->json(['success' => false, 'message' => 'Vui lòng nhập SĐT'], 400);
        }

        $customer = Customer::with(['group', 'orders' => function($q) {
            $q->latest()->limit(5)->with('items.product');
        }])
        ->where('phone', 'like', "%{$phone}%")
        ->first();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy khách hàng'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $customer
        ]);
    }
}
