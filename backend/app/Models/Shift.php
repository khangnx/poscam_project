<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    protected $fillable = [
        'user_id',
        'start_time',
        'end_time',
        'start_cash',
        'end_cash',
        'total_cash_sales',
        'total_non_cash_sales',
        'total_revenue',
        'balance_gap',
        'reason',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'start_cash' => 'decimal:2',
            'end_cash' => 'decimal:2',
            'total_cash_sales' => 'decimal:2',
            'total_non_cash_sales' => 'decimal:2',
            'total_revenue' => 'decimal:2',
            'balance_gap' => 'decimal:2',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the orders for the shift.
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
