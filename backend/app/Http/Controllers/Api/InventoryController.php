<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\InventoryLog;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class InventoryController extends Controller
{
    protected $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * Display inventory logs.
     */
    public function history(Request $request)
    {
        $query = InventoryLog::with(['material', 'user', 'order'])->latest();

        if ($request->has('material_id') && $request->filled('material_id')) {
            $query->where('material_id', $request->material_id);
        }

        if ($request->has('type') && $request->filled('type')) {
            $query->where('type', $request->type);
        }

        $logs = $query->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $logs
        ]);
    }

    /**
     * Get products with low stock.
     */
    public function lowStock()
    {
        $materials = Material::whereColumn('stock', '<=', 'min_stock')->get();

        return response()->json([
            'success' => true,
            'data' => $materials
        ]);
    }

    /**
     * Process import from supplier.
     */
    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'material_id' => 'required|exists:materials,id',
            'supplier_id' => 'required|exists:suppliers,id',
            'quantity' => 'required|numeric|min:0.0001',
            'cost_price' => 'required|numeric|min:0',
            'note' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $material = $this->inventoryService->importMaterial(
                $request->material_id,
                $request->supplier_id,
                $request->quantity,
                $request->cost_price,
                1,
                $request->note
            );

            return response()->json([
                'success' => true,
                'message' => 'Nhập hàng thành công',
                'data' => $material
            ]);
        } catch (\Exception $e) {
            Log::error('Inventory Import Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Stock adjustment (Stock count).
     */
    public function adjust(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'material_id' => 'required|exists:materials,id',
            'actual_stock' => 'required|numeric|min:0',
            'note' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $material = $this->inventoryService->adjustStock(
                $request->material_id,
                $request->actual_stock,
                $request->note
            );
            return response()->json(['success' => true, 'message' => 'Kiểm kho thành công', 'data' => $material]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Stock wastage (Disposal).
     */
    public function waste(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'material_id' => 'required|exists:materials,id',
            'quantity' => 'required|numeric|min:0.0001',
            'reason' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $material = $this->inventoryService->wasteStock(
                $request->material_id,
                $request->quantity,
                $request->reason
            );
            return response()->json(['success' => true, 'message' => 'Xuất hủy thành công', 'data' => $material]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}
