<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockImport;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StockImportController extends Controller
{
    protected InventoryService $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * Display a listing of stock imports.
     */
    public function index()
    {
        $imports = StockImport::with(['supplier', 'user', 'items.material'])
            ->latest()
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $imports
        ]);
    }

    /**
     * Store a newly created stock import.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'import_date' => 'required|date',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'items' => 'required|array|min:1',
            'items.*.material_id' => 'required|exists:materials,id',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.purchase_price' => 'required|numeric|min:0',
            'items.*.conversion_factor' => 'required|numeric|min:0.0001',
            'items.*.purchase_unit' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $totalAmount = collect($request->items)->sum(fn($i) => $i['quantity'] * $i['purchase_price']);
            
            $data = $request->all();
            $data['total_amount'] = $totalAmount;

            $stockImport = $this->inventoryService->processImport($data);

            return response()->json([
                'success' => true,
                'message' => 'Lập phiếu nhập kho thành công',
                'data' => $stockImport->load('items.material')
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi nhập kho: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Display the specified stock import.
     */
    public function show(StockImport $stockImport)
    {
        return response()->json([
            'success' => true,
            'data' => $stockImport->load(['supplier', 'user', 'items.material'])
        ]);
    }
}
