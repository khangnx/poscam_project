<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockImportItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'import_id',
        'material_id',
        'quantity',
        'purchase_unit',
        'purchase_price',
        'conversion_factor',
        'subtotal',
    ];

    /**
     * Get the import head.
     */
    public function import()
    {
        return $this->belongsTo(StockImport::class, 'import_id');
    }

    /**
     * Get the material.
     */
    public function material()
    {
        return $this->belongsTo(Material::class, 'material_id');
    }
}
