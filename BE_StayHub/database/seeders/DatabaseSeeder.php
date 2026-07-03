<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        try {
            if (config('filesystems.disks.s3.driver') === 's3') {
                $s3 = \Illuminate\Support\Facades\Storage::disk('s3');
                $bucketName = config('filesystems.disks.s3.bucket');
                if ($bucketName && !$s3->getClient()->doesBucketExistV2($bucketName)) {
                    $s3->getClient()->createBucket(['Bucket' => $bucketName]);
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Không thể tạo bucket S3/MinIO: ' . $e->getMessage());
        }

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

        $this->call(SuperAdminSeeder::class);
        $this->call(StayHubDemoSeeder::class);
    }
}
