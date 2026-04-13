<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Camera extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'name',
        'rtsp_url',
        'type',
        'location_note',
        'is_active',
        'tenant_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        // 'rtsp_url' removed to allow editing in frontend
    ];
}
