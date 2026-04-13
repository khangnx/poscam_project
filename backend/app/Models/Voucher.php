<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'discount_rate',
        'is_used',
        'expiry_date',
    ];

    protected $casts = [
        'is_used' => 'boolean',
        'expiry_date' => 'datetime',
        'discount_rate' => 'float',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
    
    // Scope để lấy các voucher hợp lệ chưa dùng và còn hạn
    public function scopeValid($query)
    {
        return $query->where('is_used', false)
                     ->where('expiry_date', '>=', now());
    }
}
