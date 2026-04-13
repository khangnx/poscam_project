<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerGroup extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'min_points',
        'discount_percent',
        'earning_rate',
        'min_points_to_redeem',
    ];

    public function customers()
    {
        return $this->hasMany(Customer::class, 'group_id');
    }
}
