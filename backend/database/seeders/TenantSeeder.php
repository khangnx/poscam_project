<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Tenant;
use App\Models\User;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create a sample Tenant
        $tenant = Tenant::updateOrCreate(
            ['domain' => 'demo.localhost'],
            [
                'name' => 'Demo Tenant',
                'settings' => ['theme' => 'dark'],
                'status' => 'active'
            ]
        );

        // 2. Create a sample User for this Tenant
        User::updateOrCreate(
            ['email' => 'admin@demo.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password123'),
                'tenant_id' => $tenant->id,
            ]
        );
    }
}
