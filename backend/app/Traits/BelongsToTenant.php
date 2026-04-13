<?php

namespace App\Traits;

use App\Services\TenantService;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToTenant
{
    /**
     * Boot the BelongsToTenant trait for a model.
     *
     * @return void
     */
    public static function bootBelongsToTenant(): void
    {
        // Global scope to automatically filter by the active tenant
        static::addGlobalScope('tenant', function (Builder $builder) {
            $tenantService = app(TenantService::class);
            
            if ($tenantId = $tenantService->getTenantId()) {
                $builder->where(app(static::class)->getTable() . '.tenant_id', $tenantId);
            }
        });

        // Automatically set the tenant_id when creating a new record
        static::creating(function ($model) {
            if (!$model->tenant_id) {
                $tenantService = app(TenantService::class);
                if ($tenantId = $tenantService->getTenantId()) {
                    $model->tenant_id = $tenantId;
                }
            }
        });
    }

    /**
     * Relationship to the Tenant model.
     */
    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }
}
