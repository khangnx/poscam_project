<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryLog extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'material_id',
        'user_id',
        'order_id',
        'type',
        'quantity',
        'old_stock',
        'new_stock',
        'note',
    ];

    /**
     * Get the material associated with the log.
     */
    public function material()
    {
        return $this->belongsTo(Material::class, 'material_id');
    }

    /**
     * Get the user who performed the action.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the order associated with this log (if applicable).
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
