<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Material;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Traits\BelongsToTenant;

class MaterialController extends Controller
{
    /**
     * Display a listing of materials.
     */
    public function index(Request $request)
    {
        $query = Material::query()->with(['category', 'supplier']);

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->has('all')) {
            $materials = $query->get();
        } else {
            $materials = $query->paginate(15);
        }

        return response()->json([
            'success' => true,
            'data' => $materials
        ]);
    }

    /**
     * Store a newly created material.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:50',
            'category_id' => 'nullable|exists:categories,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'purchase_unit' => 'nullable|string|max:20',
            'usage_unit' => 'nullable|string|max:20',
            'conversion_factor' => 'nullable|numeric|min:0.0001',
            'cost_price' => 'nullable|numeric|min:0',
            'stock' => 'nullable|numeric',
            'min_stock' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $material = Material::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Thêm nguyên liệu thành công',
            'data' => $material
        ], 201);
    }

    /**
     * Display the specified material.
     */
    public function show(Material $material)
    {
        return response()->json([
            'success' => true,
            'data' => $material->load(['category', 'supplier'])
        ]);
    }

    /**
     * Update the specified material.
     */
    public function update(Request $request, Material $material)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'sku' => 'nullable|string|max:50',
            'category_id' => 'nullable|exists:categories,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'purchase_unit' => 'nullable|string|max:20',
            'usage_unit' => 'nullable|string|max:20',
            'conversion_factor' => 'nullable|numeric|min:0.0001',
            'cost_price' => 'nullable|numeric|min:0',
            'stock' => 'nullable|numeric',
            'min_stock' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $material->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật nguyên liệu thành công',
            'data' => $material
        ]);
    }

    /**
     * Remove the specified material.
     */
    public function destroy(Material $material)
    {
        $material->delete();

        return response()->json([
            'success' => true,
            'message' => 'Xóa nguyên liệu thành công'
        ]);
    }

    /**
     * Get low stock materials.
     */
    public function lowStock()
    {
        $materials = Material::whereColumn('stock', '<=', 'min_stock')->get();

        return response()->json([
            'success' => true,
            'data' => $materials
        ]);
    }
}
