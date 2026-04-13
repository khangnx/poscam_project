<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    protected $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Product::query()->with(['category', 'recipes.material']);

        if ($request->has('category_id') && $request->category_id !== '') {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('search') && $request->search !== '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        $products = $query->latest()->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'sku' => [
                'required',
                'string',
                'max:255',
                // Unique SKU within the same tenant
                Rule::unique('products')->where(function ($query) {
                    return $query->where('tenant_id', auth()->user()->tenant_id);
                }),
            ],
            'selling_price' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'category_id' => 'nullable|exists:categories,id',
            'status' => 'required|in:active,inactive',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'image_path' => 'nullable|string',
            'recipes' => 'nullable|array',
            'recipes.*.material_id' => 'required_with:recipes|exists:materials,id',
            'recipes.*.quantity' => 'required_with:recipes|numeric|min:0.0001',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }


        $data = $request->only(['name', 'sku', 'selling_price', 'category_id', 'status']);
        
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = time() . '_' . $request->sku . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('products', $filename, 'public');
            $data['image_path'] = $path;
        } elseif ($request->has('image_path')) {
            $data['image_path'] = $request->image_path;
        }

        $data['tenant_id'] = auth()->user()->tenant_id;

        $product = Product::create($data);

        // Sync recipes if provided
        if ($request->has('recipes')) {
            foreach ($request->recipes as $recipeData) {
                if (!empty($recipeData['material_id'])) {
                    $product->recipes()->create([
                        'tenant_id'   => $product->tenant_id,
                        'material_id' => $recipeData['material_id'],
                        'quantity'    => $recipeData['quantity'],
                    ]);
                }
            }
            // Recalculate cost
            $this->inventoryService->calculateRecipeCost($product->id);
        }

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully',
            'data' => $product->load(['category', 'recipes.material'])
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        return response()->json([
            'success' => true,
            'data' => $product->load(['category', 'recipes.material'])
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'sku' => [
                'required',
                'string',
                'max:255',
                Rule::unique('products')->where(function ($query) {
                    return $query->where('tenant_id', auth()->user()->tenant_id);
                })->ignore($product->id),
            ],
            'selling_price' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'category_id' => 'nullable|exists:categories,id',
            'status' => 'required|in:active,inactive',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'image_path' => 'nullable|string',
            'recipes' => 'nullable|array',
            'recipes.*.material_id' => 'required_with:recipes|exists:materials,id',
            'recipes.*.quantity' => 'required_with:recipes|numeric|min:0.0001',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }


        $data = $request->only(['name', 'sku', 'selling_price', 'category_id', 'status']);

        if ($request->hasFile('image')) {
            // Delete old image if it's not a URL
            if ($product->image_path && !filter_var($product->image_path, FILTER_VALIDATE_URL)) {
                Storage::disk('public')->delete($product->image_path);
            }
            
            $file = $request->file('image');
            $filename = time() . '_' . $request->sku . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('products', $filename, 'public');
            $data['image_path'] = $path;
        } elseif ($request->has('image_path')) {
            $data['image_path'] = $request->image_path;
        }

        $product->update($data);

        // Sync recipes
        if ($request->has('recipes')) {
            // "Strict Validation": clear old recipes before saving new ones
            $product->recipes()->delete(); 
            
            foreach ($request->recipes as $recipeData) {
                if (!empty($recipeData['material_id'])) {
                    $product->recipes()->create([
                        'tenant_id'   => $product->tenant_id,
                        'material_id' => $recipeData['material_id'],
                        'quantity'    => $recipeData['quantity'],
                    ]);
                }
            }
            // Recalculate cost
            $this->inventoryService->calculateRecipeCost($product->id);
        }

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully',
            'data' => $product->load(['category', 'recipes.material'])
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        $product->delete(); // Soft delete

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully'
        ]);
    }
}
