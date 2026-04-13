<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes;

    protected static function booted()
    {
        static::updating(function ($product) {
            // Logic to recalculate cost_price if needed could go here
            // but usually it's triggered by Recipe changes or Material cost changes.
        });
    }

    /**
     * Recalculate and save the cost price based on current recipes and material costs.
     */
    public function updateCostFromRecipes()
    {
        $totalCost = 0;
        foreach ($this->recipes()->with('material')->get() as $recipe) {
            $totalCost += ($recipe->material->cost_price ?? 0) * $recipe->quantity;
        }
        
        $this->cost_price = $totalCost;
        $this->save();
        
        return $totalCost;
    }

    protected $fillable = [
        'tenant_id',
        'category_id',
        'name',
        'sku',
        'selling_price',
        'cost_price',
        'status',
        'image_path',
    ];

    /**
     * Get the category that owns the product.
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    protected $appends = ['margin_percentage', 'is_out_of_stock', 'is_low_stock', 'available_quantity', 'image_url'];

    /**
     * Get the full URL for the product image.
     */
    public function getImageUrlAttribute()
    {
        if (!$this->image_path) {
            return null;
        }

        if (filter_var($this->image_path, FILTER_VALIDATE_URL)) {
            return $this->image_path;
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->url($this->image_path);
    }

    /**
     * Check if the product is out of stock based on its ingredients.
     * 
     * @return bool
     */
    public function getIsOutOfStockAttribute()
    {
        // Recursively check all ingredients in the recipe
        foreach ($this->recipes as $recipe) {
            if (!$recipe->material || $recipe->material->stock < $recipe->quantity) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get the low stock status (any material below min_stock).
     * 
     * @return bool
     */
    public function getIsLowStockAttribute()
    {
        foreach ($this->recipes as $recipe) {
            if ($recipe->material && $recipe->material->stock <= $recipe->material->min_stock) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get the margin percentage.
     * 
     * @return float
     */
    public function getMarginPercentageAttribute()
    {
        if ($this->selling_price <= 0) return 0;
        return round((($this->selling_price - $this->cost_price) / $this->selling_price) * 100, 2);
    }

    /**
     * Get the available quantity based on material stock and recipe.
     * 
     * @return int
     */
    public function getAvailableQuantityAttribute()
    {
        if ($this->recipes->isEmpty()) {
            return 0;
        }

        $minPossible = PHP_INT_MAX;
        $hasValidMaterial = false;

        foreach ($this->recipes as $recipe) {
            if ($recipe->material && $recipe->quantity > 0) {
                $possible = floor($recipe->material->stock / $recipe->quantity);
                if ($possible < $minPossible) {
                    $minPossible = $possible;
                }
                $hasValidMaterial = true;
            } else {
                // If a recipe is missing a material or has 0 quantity, we can't determine availability properly
                return 0;
            }
        }

        return $hasValidMaterial ? (int)$minPossible : 0;
    }

    /**
     * Get the order items that include this product.
     */
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Products (Menu items) no longer have a direct supplier, 
     * only Materials do.
     */

    /**
     * Get the inventory logs for this product.
     */
    /**
     * Get the recipes for this product (if it's a finished good).
     */
    public function recipes()
    {
        return $this->hasMany(ProductRecipe::class, 'product_id');
    }

    /**
     * Get the ingredients (Materials) for this product.
     */
    public function ingredients()
    {
        return $this->belongsToMany(Material::class, 'product_recipes', 'product_id', 'material_id')
                    ->withPivot('quantity');
    }

    /**
     * Get the products that use this product as an ingredient.
     */
    public function usedInRecipes()
    {
        return $this->hasMany(ProductRecipe::class, 'material_id');
    }
    /**
     * Get the stock imports that include this product.
     */
    public function importItems()
    {
        return $this->hasMany(StockImportItem::class);
    }
}
