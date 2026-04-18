<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            'manage_users',
            'manage_roles',
            'manage_shifts',
            'view_shifts',
            'sell',
            'manage_products',
            'view_orders',
            'view_cameras',
            'manage_cameras',
            'view_dashboard'
        ];

        foreach ($permissions as $permission) {
            \Spatie\Permission\Models\Permission::findOrCreate($permission, 'web');
        }

        // Create roles
        $admin = \Spatie\Permission\Models\Role::findOrCreate('admin', 'web');
        $manager = \Spatie\Permission\Models\Role::findOrCreate('manager', 'web');
        $staff = \Spatie\Permission\Models\Role::findOrCreate('staff', 'web');

        // Give all permissions to admin
        $admin->syncPermissions($permissions);

        // Give some permissions to manager & staff as defaults
        $manager->syncPermissions(['view_shifts', 'sell', 'manage_products', 'view_orders', 'view_cameras', 'view_dashboard']);
        $staff->syncPermissions(['sell', 'view_orders']);

        // Assign first user as admin if exists
        $user = \App\Models\User::first();
        if ($user) {
            $user->assignRole($admin);
        }
    }
}
