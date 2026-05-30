<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'superadmin@pronojiwo.id'],
            [
                'name'     => 'Super Admin',
                'password' => bcrypt('SuperAdmin123!'),
            ]
        );
        $user->assignRole('super_admin');
    }
}
