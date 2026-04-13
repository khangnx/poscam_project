<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Material extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes;
    protected static function booted()
    {
        static::updated(function ($material) {
            if ($material->isDirty('cost_price')) {
                // Ripple effect: Update cost of any "Product" that uses this as an ingredient
                app(\App\Services\InventoryService::class)->updateDependentCosts($material->id);
            }
        });
    }


    protected $fillable = [
        'tenant_id',
        'supplier_id',
        'category_id',
        'name',
        'sku',
        'purchase_unit',
        'usage_unit',
        'conversion_factor',
        'cost_price',
        'stock',
        'min_stock',
        'status',
        'image_path',
    ];

    /**
     * Get the category that owns the material.
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the supplier that provides this material.
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the recipes that use this material.
     */
    public function recipes()
    {
        return $this->hasMany(ProductRecipe::class, 'material_id');
    }

    /**
     * Get the inventory logs for this material.
     */
    public function inventoryLogs()
    {
        return $this->hasMany(InventoryLog::class, 'material_id');
    }

    /**
     * Get the low stock status.
     * 
     * @return bool
     */
    public function getIsLowStockAttribute()
    {
        return $this->stock <= $this->min_stock;
    }
}
