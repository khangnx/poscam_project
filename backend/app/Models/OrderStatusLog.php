<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderStatusLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'user_id',
        'from_status',
        'to_status',
    ];

    /**
     * Get the order that owns the log.
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the user who executed the status change.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
