<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'amount',
        'bank_transaction_id',
        'reference_id',
        'status',
        'description',
    ];

    /**
     * Get the order associated with the payment.
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
