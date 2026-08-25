<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        /*
        |--------------------------------------------------------------------------
        | Define Permissions
        |--------------------------------------------------------------------------
        */

        $permissions = [
            // Admin
            'manage everything',

            // Supervisor
            'manage agents',
            'manage bookings',

            // Agent
            'create bookings',

            // Staff
            'provide services',

            // User
            'book appointments',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        /*
        |--------------------------------------------------------------------------
        | Create Roles
        |--------------------------------------------------------------------------
        */

        $admin      = Role::firstOrCreate(['name' => 'admin']);
        $supervisor = Role::firstOrCreate(['name' => 'supervisor']);
        $agent      = Role::firstOrCreate(['name' => 'agent']);
        $staff      = Role::firstOrCreate(['name' => 'staff']);
        $user       = Role::firstOrCreate(['name' => 'user']);

        /*
        |--------------------------------------------------------------------------
        | Assign Permissions to Roles
        |--------------------------------------------------------------------------
        */

        // Admin gets all permissions
        $admin->givePermissionTo(Permission::all());

        // Supervisor
        $supervisor->givePermissionTo(['manage agents', 'manage bookings']);

        // Agent
        $agent->givePermissionTo(['create bookings']);

        // Staff
        $staff->givePermissionTo(['provide services']);

        // User
        $user->givePermissionTo(['book appointments']);
    }
}
