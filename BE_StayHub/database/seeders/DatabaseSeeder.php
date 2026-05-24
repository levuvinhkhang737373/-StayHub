<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Admin::query()->updateOrCreate(
            ['username' => 'admin'],
            [
                'full_name' => 'Quản trị hệ thống',
                'email' => 'admin@stayhub.local',
                'phone' => '0900000000',
                'password' => '12345678',
                'role' => Admin::ROLE_SUPER_ADMIN,
                'avatar_url' => null,
                'status' => Admin::STATUS_ACTIVE,
                'gender' => Admin::GENDER_MALE,
                'address' => 'StayHub',
            ],
        );

        $this->call(StayHubDemoSeeder::class);
    }
}
