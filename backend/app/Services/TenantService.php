<?php

namespace App\Services;

use App\Models\Tenant;

class TenantService
{
    protected ?Tenant $tenant = null;

    /**
     * Set the current active tenant.
     *
     * @param  \App\Models\Tenant  $tenant
     * @return void
     */
    public function setTenant(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    /**
     * Get the current active tenant.
     *
     * @return \App\Models\Tenant|null
     */
    public function getTenant(): ?Tenant
    {
        return $this->tenant;
    }

    /**
     * Get the current active tenant's ID.
     *
     * @return int|null
     */
    public function getTenantId(): ?int
    {
        return $this->tenant?->id;
    }
}
