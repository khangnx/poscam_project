<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductRecipe extends Model
{
    use HasFactory, BelongsToTenant;

    protected static function booted()
    {
        static::saved(function ($recipe) {
            $recipe->product->updateCostFromRecipes();
        });

        static::deleted(function ($recipe) {
            $recipe->product?->updateCostFromRecipes();
        });
    }

    protected $fillable = [
        'tenant_id',
        'product_id',
        'material_id',
        'quantity',
    ];

    /**
     * Get the finished product.
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Get the material/ingredient.
     */
    public function material()
    {
        return $this->belongsTo(Material::class, 'material_id');
    }
}
