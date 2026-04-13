<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'phone',
        'name',
        'group_id',
        'points',
        'total_spent',
    ];

    public function group()
    {
        return $this->belongsTo(CustomerGroup::class, 'group_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function loyaltyLogs()
    {
        return $this->hasMany(LoyaltyLog::class);
    }
}
