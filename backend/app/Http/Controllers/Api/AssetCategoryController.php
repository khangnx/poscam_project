<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AssetCategory;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AssetCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = AssetCategory::query();

        if ($request->has('search') && $request->search !== '') {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
        }

        // Return all for dropdowns if requested
        if ($request->has('all')) {
            return response()->json([
                'success' => true,
                'data' => $query->latest()->get()
            ]);
        }

        $categories = $query->latest()->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        
        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('asset_categories')->where(fn ($query) => $query->where('tenant_id', $tenantId))
            ],
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $category = AssetCategory::create([
            'tenant_id' => $tenantId,
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Asset category created successfully',
            'data' => $category
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(AssetCategory $assetCategory)
    {
        return response()->json([
            'success' => true,
            'data' => $assetCategory
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, AssetCategory $assetCategory)
    {
        $tenantId = auth()->user()->tenant_id;

        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('asset_categories')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($assetCategory->id)
            ],
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $assetCategory->update($request->only(['name', 'description']));

        return response()->json([
            'success' => true,
            'message' => 'Asset category updated successfully',
            'data' => $assetCategory
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AssetCategory $assetCategory)
    {
        // Check if there are any assets in this category
        $assetCount = $assetCategory->assets()->count();
        if ($assetCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Không thể xóa danh mục này vì đang có {$assetCount} tài sản trực thuộc. Vui lòng chuyển hoặc xóa tài sản trước."
            ], 422);
        }

        $assetCategory->delete();

        return response()->json([
            'success' => true,
            'message' => 'Asset category deleted successfully'
        ]);
    }
}
