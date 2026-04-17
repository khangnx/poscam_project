<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'shift_id',
        'customer_id',
        'customer_name',
        'total_amount',
        'discount_amount',
        'points_earned',
        'status',
        'payment_method',
        'completed_at',
        'preparer_id',
    ];

    /**
     * Get the shift associated with the order.
     */
    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * Get the user who created the order.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the order items for the order.
     */
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the user who is preparing or prepared the order.
     */
    public function preparer()
    {
        return $this->belongsTo(User::class, 'preparer_id');
    }

    /**
     * Get the status logs for the order.
     */
    public function statusLogs()
    {
        return $this->hasMany(OrderStatusLog::class);
    }
}
