<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Region;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RegionSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = Admin::query()
            ->where('username', 'admin')
            ->value('id');

        $hoChiMinh = Region::query()->updateOrCreate(
            ['code' => 'HCM'],
            [
                'parent_id' => null,
                'name' => 'Thành phố Hồ Chí Minh',
                'path' => 'Thành phố Hồ Chí Minh',
                'slug' => Str::slug('Thành phố Hồ Chí Minh'),
                'description' => 'Thành phố Hồ Chí Minh.',
                'is_active' => Region::ACTIVE,
                'created_by' => $adminId,
            ],
        );

        collect([
            [
                'code' => 'HCM-SG',
                'name' => 'Phường Sài Gòn',
                'description' => 'Phường Bến Nghé, một phần phường Đa Kao và Nguyễn Thái Bình.',
            ],
            [
                'code' => 'HCM-TD',
                'name' => 'Phường Tân Định',
                'description' => 'Phường Tân Định và một phần phường Đa Kao.',
            ],
            [
                'code' => 'HCM-BT',
                'name' => 'Phường Bến Thành',
                'description' => 'Các phường Bến Thành, Phạm Ngũ Lão, một phần phường Cầu Ông Lãnh và Nguyễn Thái Bình.',
            ],
            [
                'code' => 'HCM-COL',
                'name' => 'Phường Cầu Ông Lãnh',
                'description' => 'Các phường Nguyễn Cư Trinh, Cầu Kho, Cô Giang, một phần phường Cầu Ông Lãnh.',
            ],
            [
                'code' => 'HCM-BC',
                'name' => 'Phường Bàn Cờ',
                'description' => 'Các phường 1, 2, 3, 5, một phần phường 4 thuộc Quận 3.',
            ],
        ])->each(function (array $region) use ($adminId, $hoChiMinh): void {
            Region::query()->updateOrCreate(
                ['code' => $region['code']],
                [
                    'parent_id' => $hoChiMinh->id,
                    'name' => $region['name'],
                    'path' => $hoChiMinh->name . ' > ' . $region['name'],
                    'slug' => Str::slug($region['name']),
                    'description' => $region['description'],
                    'is_active' => Region::ACTIVE,
                    'created_by' => $adminId,
                ],
            );
        });
    }
}
