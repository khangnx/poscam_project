<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrendSuggestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_name',
        'trend_score',
        'source_url',
        'status',
        'product_id',
        'recommendation_reason',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
