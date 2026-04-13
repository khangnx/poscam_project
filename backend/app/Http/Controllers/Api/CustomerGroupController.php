<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerGroup;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class CustomerGroupController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $groups = CustomerGroup::withCount('customers')->get();
        return response()->json([
            'success' => true,
            'data'    => $groups
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'                 => 'required|string|max:255',
            'min_points'           => 'required|integer|min:0',
            'discount_percent'     => 'required|numeric|min:0|max:100',
            'earning_rate'         => 'required|numeric|min:0',
            'min_points_to_redeem' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $group = CustomerGroup::create([
            'tenant_id'            => auth()->user()->tenant_id,
            'name'                 => $request->name,
            'min_points'           => $request->min_points,
            'discount_percent'     => $request->discount_percent,
            'earning_rate'         => $request->earning_rate,
            'min_points_to_redeem' => $request->min_points_to_redeem ?? 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Nhóm khách hàng đã được tạo',
            'data'    => $group
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(CustomerGroup $customerGroup)
    {
        return response()->json([
            'success' => true,
            'data'    => $customerGroup->load('customers')
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, CustomerGroup $customerGroup)
    {
        $validator = Validator::make($request->all(), [
            'name'                 => 'nullable|string|max:255',
            'min_points'           => 'nullable|integer|min:0',
            'discount_percent'     => 'nullable|numeric|min:0|max:100',
            'earning_rate'         => 'nullable|numeric|min:0',
            'min_points_to_redeem' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $customerGroup->update($request->only([
            'name', 'min_points', 'discount_percent', 'earning_rate', 'min_points_to_redeem'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật thành công',
            'data'    => $customerGroup
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, CustomerGroup $customerGroup)
    {
        $customersCount = $customerGroup->customers()->count();

        if ($customersCount > 0) {
            $validator = Validator::make($request->all(), [
                'migrate_to_id' => 'required|exists:customer_groups,id|not_in:' . $customerGroup->id,
            ], [
                'migrate_to_id.required' => 'Vui lòng chọn nhóm mới để chuyển khách hàng sang trước khi xóa.',
                'migrate_to_id.exists'   => 'Nhóm đích không tồn tại.',
                'migrate_to_id.not_in'   => 'Không thể chuyển vào chính nhóm sắp xóa.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nhóm này đang có khách hàng. Bạn cần chọn nhóm mới để chuyển họ sang.',
                    'errors'  => $validator->errors()
                ], 422);
            }

            try {
                DB::beginTransaction();
                
                // Migrate customers
                Customer::where('group_id', $customerGroup->id)
                    ->update(['group_id' => $request->migrate_to_id]);

                $customerGroup->delete();
                
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Lỗi khi xóa nhóm: ' . $e->getMessage()], 400);
            }
        } else {
            $customerGroup->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Nhóm khách hàng đã được xóa'
        ]);
    }
}
