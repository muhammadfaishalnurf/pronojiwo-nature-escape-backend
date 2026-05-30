<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            'view destinations',
            'manage destinations',
            'view tickets',
            'manage tickets',
            'view users',
            'manage users',
            'view reviews',
            'manage reviews',
            'view dashboard',
            'manage settings',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }
        $user = Role::firstOrCreate(['name' => 'user']);
        $user->syncPermissions(['view destinations', 'view tickets', 'view reviews']);

        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions([
            'view destinations',
            'manage destinations',
            'view tickets',
            'manage tickets',
            'view users',
            'view reviews',
            'manage reviews',
            'view dashboard',
        ]);

        $superAdmin = Role::firstOrCreate(['name' => 'super_admin']);
        $superAdmin->syncPermissions(Permission::all());
    }
}
