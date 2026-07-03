<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\Admin::updateOrCreate(
            ['username' => 'superadmin1'],
            [
                'full_name' => 'Super Admin 1',
                'email' => 'superadmin1@stayhub.local',
                'phone' => '0900000001',
                'password' => \Illuminate\Support\Facades\Hash::make('1'),
                'role' => \App\Models\Admin::ROLE_SUPER_ADMIN,
                'status' => \App\Models\Admin::STATUS_ACTIVE,
            ]
        );
    }
}
