<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\AssetTemplate;
use App\Models\Building;
use App\Models\BuildingImage;
use App\Models\Contract;
use App\Models\ContractDepositTransaction;
use App\Models\ContractTenant;
use App\Models\ContractVehicle;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\MaintenanceRequest;
use App\Models\MeterDevice;
use App\Models\MeterReading;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Region;
use App\Models\Room;
use App\Models\RoomImage;
use App\Models\RoomMovement;
use App\Models\RoomType;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StayHubDemoSeeder extends Seeder
{
    private CarbonImmutable $now;

    public function run(): void
    {
        $this->now = CarbonImmutable::now();

        DB::transaction(function (): void {
            $admins = $this->seedAdmins();

            $this->call(RegionSeeder::class);

            $regions = $this->getRegions();
            $buildings = $this->seedBuildings($admins, $regions);
            $this->seedBuildingImages($admins, $buildings);

            $roomTypes = $this->seedRoomTypes($admins);
            $rooms = $this->seedRooms($admins, $buildings, $roomTypes);
            $this->seedRoomImages($admins, $rooms);

            $assets = $this->seedAssetTemplates($admins, $buildings);
            $this->seedRoomAssets($assets, $rooms);

            $services = $this->seedServices($admins);
            $this->seedServicePrices($buildings, $services);

            $tenants = $this->seedTenants($admins, $buildings);
            $contracts = $this->seedContracts($admins, $rooms, $tenants);
            $this->seedContractTenants($admins, $contracts, $tenants);

            $vehicles = $this->seedVehicles($tenants);
            $this->seedContractVehicles($contracts, $vehicles);
            $this->seedContractDeposits($admins, $contracts);
            $this->seedRoomMovements($admins, $contracts, $rooms, $tenants);

            $meters = $this->seedMeterDevices($rooms, $services);
            $readings = $this->seedMeterReadings($admins, $meters);

            $invoices = $this->seedInvoices($admins, $contracts, $rooms);
            $this->seedInvoiceItems($invoices, $readings, $services);
            $this->seedPayments($admins, $invoices);

            $maintenanceRequests = $this->seedMaintenanceRequests($admins, $rooms, $tenants);
            $this->seedMaintenanceFeedbacks($maintenanceRequests, $tenants);
            $this->seedMaintenanceLogs($admins, $maintenanceRequests);

            $notifications = $this->seedNotifications($admins, $buildings, $rooms, $tenants);
            $this->seedNotificationReads($notifications, $tenants);

            $expenseCategories = $this->seedExpenseCategories($admins);
            $this->seedExpenses($admins, $buildings, $rooms, $expenseCategories);
            $this->seedSettings($admins, $buildings);
            $this->seedAdminLogs($admins, $buildings, $rooms, $tenants, $contracts, $invoices);
            $this->seedExpandedDemoData($admins, $regions, $roomTypes, $assets, $services, $expenseCategories);
        });
    }

    private function seedAdmins(): array
    {
        $admins = [
            'super' => $this->upsertAndGetId('admins', ['email' => 'admin@stayhub.local'], [
                'username' => 'admin',
                'full_name' => 'Quản trị hệ thống',
                'email' => 'admin@stayhub.local',
                'phone' => '0900000000',
                'password' => Hash::make('12345678'),
                'role' => Admin::ROLE_SUPER_ADMIN,
                'avatar_url' => '/storage/admins/admin.png',
                'status' => Admin::STATUS_ACTIVE,
                'gender' => Admin::GENDER_MALE,
                'address' => 'TP.HCM',
                'image_path_faceid' => null,
                'created_faceid_at' => null,
                'updated_faceid_at' => null,
                ...$this->timestamps(),
            ]),
        ];

        foreach ($this->buildingManagerProfiles() as $buildingKey => $manager) {
            $admins[$buildingKey] = $this->upsertAndGetId('admins', ['email' => $manager['email']], [
                'username' => $manager['username'],
                'full_name' => $manager['full_name'],
                'email' => $manager['email'],
                'phone' => $manager['phone'],
                'password' => Hash::make('12345678'),
                'role' => Admin::ROLE_BUILDING_MANAGER,
                'avatar_url' => "/storage/admins/{$manager['avatar']}.png",
                'status' => Admin::STATUS_ACTIVE,
                'gender' => $manager['gender'],
                'address' => $manager['address'],
                'image_path_faceid' => "/storage/faceid/{$manager['avatar']}.jpg",
                'created_faceid_at' => $this->now->subDays(20),
                'updated_faceid_at' => $this->now->subDays(10),
                ...$this->timestamps(),
            ]);
        }

        $admins['manager_sg'] = $admins['sg_central'];
        $admins['tech_sg'] = $this->upsertAndGetId('admins', ['email' => 'tech.sg@stayhub.local'], [
            'username' => 'tech_sg',
            'full_name' => 'Trần Quốc Huy',
            'email' => 'tech.sg@stayhub.local',
            'phone' => '0900000002',
            'password' => Hash::make('12345678'),
            'role' => Admin::ROLE_TECHNICIAN,
            'avatar_url' => '/storage/admins/tech-sg.png',
            'status' => Admin::STATUS_ACTIVE,
            'gender' => Admin::GENDER_MALE,
            'address' => 'Phường Bến Thành, TP.HCM',
            'image_path_faceid' => null,
            'created_faceid_at' => null,
            'updated_faceid_at' => null,
            ...$this->timestamps(),
        ]);

        return $admins;
    }

    private function buildingManagerProfiles(): array
    {
        return [
            'sg_central' => [
                'username' => 'manager_sg',
                'full_name' => 'Nguyễn Minh Quân',
                'email' => 'manager.sg@stayhub.local',
                'phone' => '0900000001',
                'avatar' => 'manager-sg',
                'gender' => Admin::GENDER_MALE,
                'address' => 'Phường Sài Gòn, TP.HCM',
            ],
            'td_garden' => [
                'username' => 'manager_td_garden',
                'full_name' => 'Lê Thu Hà',
                'email' => 'manager.td.garden@stayhub.local',
                'phone' => '0900000102',
                'avatar' => 'manager-td-garden',
                'gender' => Admin::GENDER_FEMALE,
                'address' => 'Phường Tân Định, TP.HCM',
            ],
            'bc_studio' => [
                'username' => 'manager_bc_studio',
                'full_name' => 'Phạm Gia Bảo',
                'email' => 'manager.bc.studio@stayhub.local',
                'phone' => '0900000103',
                'avatar' => 'manager-bc-studio',
                'gender' => Admin::GENDER_MALE,
                'address' => 'Phường Bàn Cờ, TP.HCM',
            ],
            'bt_tower' => [
                'username' => 'manager_bt_tower',
                'full_name' => 'Trần Ngọc Mai',
                'email' => 'manager.bt.tower@stayhub.local',
                'phone' => '0900000104',
                'avatar' => 'manager-bt-tower',
                'gender' => Admin::GENDER_FEMALE,
                'address' => 'Phường Bến Thành, TP.HCM',
            ],
            'col_river' => [
                'username' => 'manager_col_river',
                'full_name' => 'Võ Quốc Nam',
                'email' => 'manager.col.river@stayhub.local',
                'phone' => '0900000105',
                'avatar' => 'manager-col-river',
                'gender' => Admin::GENDER_MALE,
                'address' => 'Phường Cầu Ông Lãnh, TP.HCM',
            ],
            'sg_lux' => [
                'username' => 'manager_sg_lux',
                'full_name' => 'Đỗ Thanh Vy',
                'email' => 'manager.sg.lux@stayhub.local',
                'phone' => '0900000106',
                'avatar' => 'manager-sg-lux',
                'gender' => Admin::GENDER_FEMALE,
                'address' => 'Phường Sài Gòn, TP.HCM',
            ],
            'td_home' => [
                'username' => 'manager_td_home',
                'full_name' => 'Bùi Hoàng Linh',
                'email' => 'manager.td.home@stayhub.local',
                'phone' => '0900000107',
                'avatar' => 'manager-td-home',
                'gender' => Admin::GENDER_FEMALE,
                'address' => 'Phường Tân Định, TP.HCM',
            ],
            'bc_square' => [
                'username' => 'manager_bc_square',
                'full_name' => 'Hồ Tuấn Sơn',
                'email' => 'manager.bc.square@stayhub.local',
                'phone' => '0900000108',
                'avatar' => 'manager-bc-square',
                'gender' => Admin::GENDER_MALE,
                'address' => 'Phường Bàn Cờ, TP.HCM',
            ],
        ];
    }

    private function getRegions(): array
    {
        return [
            'sai_gon' => (int) DB::table('regions')->where('code', 'HCM-SG')->value('id'),
            'tan_dinh' => (int) DB::table('regions')->where('code', 'HCM-TD')->value('id'),
            'ben_thanh' => (int) DB::table('regions')->where('code', 'HCM-BT')->value('id'),
            'cau_ong_lanh' => (int) DB::table('regions')->where('code', 'HCM-COL')->value('id'),
            'ban_co' => (int) DB::table('regions')->where('code', 'HCM-BC')->value('id'),
        ];
    }

    private function seedBuildings(array $admins, array $regions): array
    {
        $rows = [
            'sg_central' => ['HCM-SG-01', $regions['sai_gon'], 'StayHub Sài Gòn Central', '58 Nguyễn Huệ, Phường Sài Gòn, TP.HCM', 6, Building::GENDER_POLICY_MIXED],
            'td_garden' => ['HCM-TD-01', $regions['tan_dinh'], 'StayHub Tân Định Garden', '22 Hai Bà Trưng, Phường Tân Định, TP.HCM', 5, Building::GENDER_POLICY_FEMALE],
            'bc_studio' => ['HCM-BC-01', $regions['ban_co'], 'StayHub Bàn Cờ Studio', '15 Cao Thắng, Phường Bàn Cờ, TP.HCM', 7, Building::GENDER_POLICY_MIXED],
        ];

        return collect($rows)->mapWithKeys(fn (array $row, string $key): array => [
            $key => $this->upsertAndGetId('buildings', ['slug' => Str::slug($row[2])], [
                'region_id' => $row[1],
                'manager_admin_id' => $admins[$key],
                'name' => $row[2],
                'slug' => Str::slug($row[2]),
                'address' => $row[3],
                'total_floors' => $row[4],
                'gender_policy' => $row[5],
                'description' => 'Tòa nhà mẫu phục vụ kiểm thử quy trình vận hành StayHub.',
                'status' => Building::STATUS_ACTIVE,
                'created_by' => $admins['super'],
                ...$this->timestamps(),
            ]),
        ])->all();
    }

    private function seedBuildingImages(array $admins, array $buildings): void
    {
        foreach ($buildings as $key => $buildingId) {
            foreach ([1, 2] as $index) {
                $this->updateOrInsert('building_images', [
                    'building_id' => $buildingId,
                    'image_path' => "/storage/demo/buildings/{$key}-{$index}.jpg",
                ], [
                    'is_primary' => $index === 1 ? BuildingImage::PRIMARY : BuildingImage::NOT_PRIMARY,
                    'sort_order' => $index,
                    'status' => BuildingImage::STATUS_VISIBLE,
                    'uploaded_by' => $admins['manager_sg'],
                    ...$this->timestamps(),
                ]);
            }
        }
    }

    private function seedRoomTypes(array $admins): array
    {
        $rows = [
            'standard' => ['Phòng tiêu chuẩn', 'Phòng đầy đủ nội thất cơ bản, phù hợp sinh viên và nhân viên văn phòng.'],
            'premium' => ['Phòng cao cấp', 'Phòng rộng, có cửa sổ lớn, nội thất tốt và khu bếp riêng.'],
            'studio' => ['Căn hộ studio', 'Studio khép kín, riêng tư, phù hợp người đi làm.'],
        ];

        return collect($rows)->mapWithKeys(fn (array $row, string $key): array => [
            $key => $this->upsertAndGetId('room_types', ['name' => $row[0]], [
                'slug' => Str::slug($row[0]),
                'description' => $row[1],
                'status' => RoomType::STATUS_ACTIVE,
                'created_by' => $admins['super'],
                ...$this->timestamps(),
            ]),
        ])->all();
    }

    private function seedRooms(array $admins, array $buildings, array $roomTypes): array
    {
        $rows = [
            'sg_a101' => [$buildings['sg_central'], $roomTypes['standard'], 'A101', 1, 24.5, 4500000, 2, 2, Room::STATUS_ACTIVE],
            'sg_a102' => [$buildings['sg_central'], $roomTypes['standard'], 'A102', 1, 23.0, 4300000, 2, 0, Room::STATUS_ACTIVE],
            'td_b201' => [$buildings['td_garden'], $roomTypes['premium'], 'B201', 2, 30.0, 5200000, 3, 1, Room::STATUS_ACTIVE],
            'td_b202' => [$buildings['td_garden'], $roomTypes['premium'], 'B202', 2, 31.0, 5400000, 3, 0, Room::STATUS_MAINTENANCE],
            'bc_c301' => [$buildings['bc_studio'], $roomTypes['studio'], 'C301', 3, 35.0, 6800000, 2, 2, Room::STATUS_ACTIVE],
            'bc_c302' => [$buildings['bc_studio'], $roomTypes['studio'], 'C302', 3, 34.0, 6600000, 2, 0, Room::STATUS_ACTIVE],
        ];

        return collect($rows)->mapWithKeys(fn (array $row, string $key): array => [
            $key => $this->upsertAndGetId('rooms', ['building_id' => $row[0], 'slug' => Str::slug($row[2])], [
                'room_type_id' => $row[1],
                'room_number' => $row[2],
                'floor' => $row[3],
                'area_m2' => $row[4],
                'base_price' => $row[5],
                'max_occupants' => $row[6],
                'current_occupants' => $row[7],
                'status' => $row[8],
                'description' => 'Phòng mẫu có dữ liệu tài sản, hợp đồng và hóa đơn đi kèm.',
                'created_by' => $admins['manager_sg'],
                ...$this->timestamps(),
            ]),
        ])->all();
    }

    private function seedRoomImages(array $admins, array $rooms): void
    {
        foreach ($rooms as $key => $roomId) {
            foreach ([1, 2] as $index) {
                $this->updateOrInsert('room_images', [
                    'room_id' => $roomId,
                    'image_path' => "/storage/demo/rooms/{$key}-{$index}.jpg",
                ], [
                    'is_primary' => $index === 1 ? RoomImage::PRIMARY : RoomImage::NOT_PRIMARY,
                    'sort_order' => $index,
                    'status' => RoomImage::STATUS_VISIBLE,
                    'uploaded_by' => $admins['manager_sg'],
                    ...$this->timestamps(),
                ]);
            }
        }
    }

    private function seedAssetTemplates(array $admins, array $buildings): array
    {
        $rows = [
            'aircon' => ['Máy lạnh', AssetTemplate::UNIT_UNIT],
            'fridge' => ['Tủ lạnh', AssetTemplate::UNIT_UNIT],
            'bed' => ['Giường', AssetTemplate::UNIT_UNIT],
            'wardrobe' => ['Tủ quần áo', AssetTemplate::UNIT_UNIT],
            'desk' => ['Bàn học', AssetTemplate::UNIT_UNIT],
        ];

        return collect($rows)->mapWithKeys(fn (array $row, string $key): array => [
            $key => $this->upsertAndGetId('asset_templates', ['name' => $row[0]], [
                'slug' => Str::slug($row[0]),
                'default_unit_name' => $row[1],
                'description' => 'Danh mục tài sản mẫu dùng khi bàn giao phòng.',
                'status' => AssetTemplate::STATUS_ACTIVE,
                'created_by' => $admins['super'],
                ...$this->timestamps(),
            ]),
        ])->all();
    }

    private function seedRoomAssets(array $assets, array $rooms): void
    {
        $rows = [
            [$rooms['sg_a101'], $assets['aircon'], 1, 8500000, 'Máy lạnh hoạt động tốt.'],
            [$rooms['sg_a101'], $assets['bed'], 2, 3000000, 'Hai giường đơn.'],
            [$rooms['sg_a101'], $assets['wardrobe'], 1, 2500000, null],
            [$rooms['td_b201'], $assets['fridge'], 1, 4200000, null],
            [$rooms['td_b201'], $assets['aircon'], 1, 9000000, null],
            [$rooms['bc_c301'], $assets['aircon'], 1, 9500000, null],
            [$rooms['bc_c301'], $assets['desk'], 2, 1800000, 'Bàn học gỗ.'],
        ];

        foreach ($rows as $row) {
            $this->updateOrInsert('room_assets', ['room_id' => $row[0], 'asset_template_id' => $row[1]], [
                'quantity' => $row[2],
                'price' => $row[3],
                'note' => $row[4],
                ...$this->timestamps(),
            ]);
        }
    }

    private function seedServices(array $admins): array
    {
        $rows = [
            'electric' => ['Điện sinh hoạt', Service::CHARGE_METHOD_BY_METER, 'kWh', true],
            'water' => ['Nước sinh hoạt', Service::CHARGE_METHOD_BY_METER, 'm³', true],
            'internet' => ['Internet', Service::CHARGE_METHOD_BY_ROOM, 'phòng', true],
            'trash' => ['Phí rác', Service::CHARGE_METHOD_BY_PERSON, 'người', true],
            'parking' => ['Gửi xe', Service::CHARGE_METHOD_BY_VEHICLE, 'xe', false],
            'cleaning' => ['Vệ sinh khu vực chung', Service::CHARGE_METHOD_BY_ROOM, 'phòng', true],
        ];

        return collect($rows)->mapWithKeys(fn (array $row, string $key): array => [
            $key => $this->upsertAndGetId('services', ['slug' => Str::slug($row[0])], [
                'name' => $row[0],
                'slug' => Str::slug($row[0]),
                'charge_method' => $row[1],
                'unit_name' => $row[2],
                'is_required' => $row[3],
                'is_active' => Service::ACTIVE,
                'created_by' => $admins['super'],
                ...$this->timestamps(),
            ]),
        ])->all();
    }

    private function seedServicePrices(array $buildings, array $services): void
    {
        $prices = [
            'electric' => 4000,
            'water' => 18000,
            'internet' => 120000,
            'trash' => 30000,
            'parking' => 150000,
            'cleaning' => 50000,
        ];

        foreach ($buildings as $buildingId) {
            foreach ($prices as $serviceKey => $price) {
                $this->updateOrInsert('service_prices', [
                    'service_id' => $services[$serviceKey],
                    'building_id' => $buildingId,
                    'effective_from' => '2026-01-01',
                ], [
                    'price' => $price,
                    'effective_to' => null,
                    'status' => ServicePrice::STATUS_ACTIVE,
                    ...$this->timestamps(),
                ]);
            }
        }
    }

    private function seedTenants(array $admins, array $buildings): array
    {
        $rows = [
            'an' => ['Lê Hoàng An', Tenant::GENDER_MALE, '1999-04-12', '0911000001', 'an.le@example.com', 'tenant_an', '079099000001', 'sg_central'],
            'binh' => ['Phạm Ngọc Bình', Tenant::GENDER_FEMALE, '2001-08-21', '0911000002', 'binh.pham@example.com', 'tenant_binh', '079101000002', 'sg_central'],
            'chi' => ['Võ Minh Chi', Tenant::GENDER_FEMALE, '2000-12-05', '0911000003', 'chi.vo@example.com', 'tenant_chi', '079100000003', 'td_garden'],
            'duy' => ['Đặng Quốc Duy', Tenant::GENDER_MALE, '1998-03-18', '0911000004', 'duy.dang@example.com', 'tenant_duy', '079098000004', 'bc_studio'],
            'em' => ['Nguyễn Thảo Em', Tenant::GENDER_FEMALE, '2002-10-30', '0911000005', 'em.nguyen@example.com', 'tenant_em', '079102000005', 'bc_studio'],
        ];

        return collect($rows)->mapWithKeys(fn (array $row, string $key): array => [
            $key => $this->upsertAndGetId('tenants', ['username' => $row[5]], [
                'building_id' => $buildings[$row[7]],
                'created_by' => $admins[$row[7]],
                'full_name' => $row[0],
                'gender' => $row[1],
                'date_of_birth' => $row[2],
                'phone' => $row[3],
                'email' => $row[4],
                'password' => Hash::make('12345678'),
                'permanent_address' => 'TP.HCM',
                'current_address' => 'Đang thuê tại StayHub',
                'avatar_url' => "/storage/demo/tenants/{$key}.jpg",
                'status' => Tenant::STATUS_RENTING,
                'identity_type' => 1,
                'identity_number' => $row[6],
                'front_image_url' => "/storage/demo/identity/{$key}-front.jpg",
                'back_image_url' => "/storage/demo/identity/{$key}-back.jpg",
                ...$this->timestamps(),
            ]),
        ])->all();
    }

    private function seedContracts(array $admins, array $rooms, array $tenants): array
    {
        $rows = [
            'sg_a101' => ['HD-2026-0001', $rooms['sg_a101'], $tenants['an'], '2026-05-01', '2027-04-30', 5, 4500000, 9000000],
            'td_b201' => ['HD-2026-0002', $rooms['td_b201'], $tenants['chi'], '2026-05-01', '2027-04-30', 5, 5200000, 10400000],
            'bc_c301' => ['HD-2026-0003', $rooms['bc_c301'], $tenants['duy'], '2026-05-01', '2027-04-30', 5, 6800000, 13600000],
        ];

        return collect($rows)->mapWithKeys(fn (array $row, string $key): array => [
            $key => $this->upsertAndGetId('contracts', ['contract_code' => $row[0]], [
                'room_id' => $row[1],
                'start_date' => $row[3],
                'end_date' => $row[4],
                'actual_end_date' => null,
                'billing_cycle_day' => $row[5],
                'room_price' => $row[6],
                'deposit_amount' => $row[7],
                'status' => Contract::STATUS_ACTIVE,
                'contract_files' => $this->json(["/storage/demo/contracts/{$row[0]}.pdf"]),
                'note' => 'Hợp đồng mẫu đang hiệu lực.',
                'created_by' => $admins['manager_sg'],
                ...$this->timestamps(),
            ]),
        ])->all();
    }

    private function seedContractTenants(array $admins, array $contracts, array $tenants): void
    {
        $rows = [
            [$contracts['sg_a101'], $tenants['an']],
            [$contracts['sg_a101'], $tenants['binh']],
            [$contracts['td_b201'], $tenants['chi']],
            [$contracts['bc_c301'], $tenants['duy']],
            [$contracts['bc_c301'], $tenants['em']],
        ];

        foreach ($rows as $row) {
            $this->updateOrInsert('contract_tenants', ['contract_id' => $row[0], 'tenant_id' => $row[1]], [
                'join_date' => '2026-05-01',
                'leave_date' => null,
                'billing_start_date' => '2026-05-01',
                'billing_end_date' => null,
                'is_staying' => ContractTenant::STAYING,
                'created_by' => $admins['manager_sg'],
                ...$this->timestamps(),
            ]);
        }
    }

    private function seedVehicles(array $tenants): array
    {
        $rows = [
            'an_bike' => [$tenants['an'], Vehicle::VEHICLE_TYPE_MOTORBIKE, '59A1-123.45', 'Honda Vision', 'Trắng'],
            'chi_bike' => [$tenants['chi'], Vehicle::VEHICLE_TYPE_MOTORBIKE, '59B2-678.90', 'Yamaha Janus', 'Đen'],
            'duy_electric' => [$tenants['duy'], Vehicle::VEHICLE_TYPE_ELECTRIC, '59E1-456.78', 'VinFast Evo', 'Xanh'],
        ];

        return collect($rows)->mapWithKeys(fn (array $row, string $key): array => [
            $key => $this->upsertAndGetId('vehicles', ['license_plate' => $row[2]], [
                'tenant_id' => $row[0],
                'vehicle_type' => $row[1],
                'brand' => $row[3],
                'color' => $row[4],
                'is_active' => Vehicle::ACTIVE,
                ...$this->timestamps(),
            ]),
        ])->all();
    }

    private function seedContractVehicles(array $contracts, array $vehicles): void
    {
        $rows = [
            [$contracts['sg_a101'], $vehicles['an_bike'], 150000],
            [$contracts['td_b201'], $vehicles['chi_bike'], 150000],
            [$contracts['bc_c301'], $vehicles['duy_electric'], 180000],
        ];

        foreach ($rows as $row) {
            $this->updateOrInsert('contract_vehicles', ['contract_id' => $row[0], 'vehicle_id' => $row[1]], [
                'started_at' => '2026-05-01',
                'ended_at' => null,
                'billing_start_date' => '2026-05-01',
                'billing_end_date' => null,
                'monthly_fee' => $row[2],
                'charge_policy' => ContractVehicle::CHARGE_POLICY_MONTHLY,
                'is_active' => ContractVehicle::ACTIVE,
                ...$this->timestamps(),
            ]);
        }
    }

    private function seedContractDeposits(array $admins, array $contracts): void
    {
        $rows = [
            [$contracts['sg_a101'], 9000000],
            [$contracts['td_b201'], 10400000],
            [$contracts['bc_c301'], 13600000],
        ];

        foreach ($rows as $index => $row) {
            $this->updateOrInsert('contract_deposit_transactions', [
                'contract_id' => $row[0],
                'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_COLLECT,
            ], [
                'amount' => $row[1],
                'transaction_date' => '2026-05-01',
                'payment_method' => $index === 0 ? ContractDepositTransaction::PAYMENT_METHOD_CASH : ContractDepositTransaction::PAYMENT_METHOD_BANK_TRANSFER,
                'note' => 'Thu cọc khi ký hợp đồng.',
                'created_by' => $admins['manager_sg'],
                'created_at' => $this->now,
            ]);
        }
    }

    private function seedRoomMovements(array $admins, array $contracts, array $rooms, array $tenants): void
    {
        $this->updateOrInsert('room_movements', [
            'tenant_id' => $tenants['em'],
            'contract_id' => $contracts['bc_c301'],
            'movement_type' => RoomMovement::MOVEMENT_TYPE_TRANSFER,
        ], [
            'from_room_id' => $rooms['bc_c302'],
            'to_room_id' => $rooms['bc_c301'],
            'movement_date' => '2026-05-01 09:00:00',
            'old_room_final_amount' => 0,
            'transfer_fee' => 0,
            'deposit_transfer_amount' => 0,
            'deposit_refund_amount' => 0,
            'deduction_amount' => 0,
            'final_electric_reading' => null,
            'final_water_reading' => null,
            'note' => 'Chuyển vào ở cùng hợp đồng đại diện.',
            'created_by' => $admins['manager_sg'],
            'created_at' => $this->now,
        ]);
    }

    private function seedMeterDevices(array $rooms, array $services): array
    {
        $rows = [
            'sg_a101_electric' => [$rooms['sg_a101'], $services['electric'], 'DIEN-SG-A101', MeterDevice::METER_TYPE_ELECTRIC, 1280],
            'sg_a101_water' => [$rooms['sg_a101'], $services['water'], 'NUOC-SG-A101', MeterDevice::METER_TYPE_WATER, 320],
            'td_b201_electric' => [$rooms['td_b201'], $services['electric'], 'DIEN-TD-B201', MeterDevice::METER_TYPE_ELECTRIC, 900],
            'td_b201_water' => [$rooms['td_b201'], $services['water'], 'NUOC-TD-B201', MeterDevice::METER_TYPE_WATER, 210],
            'bc_c301_electric' => [$rooms['bc_c301'], $services['electric'], 'DIEN-BC-C301', MeterDevice::METER_TYPE_ELECTRIC, 1500],
            'bc_c301_water' => [$rooms['bc_c301'], $services['water'], 'NUOC-BC-C301', MeterDevice::METER_TYPE_WATER, 410],
        ];

        return collect($rows)->mapWithKeys(fn (array $row, string $key): array => [
            $key => $this->upsertAndGetId('meter_devices', ['room_id' => $row[0], 'service_id' => $row[1]], [
                'meter_type' => $row[3],
                'initial_reading' => $row[4],
                'installed_at' => '2026-05-01',
                'replaced_by_meter_id' => null,
                'final_reading' => null,
                'status' => MeterDevice::STATUS_ACTIVE,
                'image_path' => "/storage/demo/meters/{$row[2]}.jpg",
                'note' => 'Công tơ đang sử dụng.',
                ...$this->timestamps(),
            ]),
        ])->all();
    }

    private function seedMeterReadings(array $admins, array $meters): array
    {
        $rows = [
            'sg_a101_electric' => [$meters['sg_a101_electric'], 1280, 1400, 120],
            'sg_a101_water' => [$meters['sg_a101_water'], 320, 340, 20],
            'td_b201_electric' => [$meters['td_b201_electric'], 900, 990, 90],
            'td_b201_water' => [$meters['td_b201_water'], 210, 222, 12],
            'bc_c301_electric' => [$meters['bc_c301_electric'], 1500, 1660, 160],
            'bc_c301_water' => [$meters['bc_c301_water'], 410, 432, 22],
        ];

        return collect($rows)->mapWithKeys(fn (array $row, string $key): array => [
            $key => $this->upsertAndGetId('meter_readings', ['meter_device_id' => $row[0], 'billing_year' => 2026, 'billing_month' => 5], [
                'previous_reading' => $row[1],
                'current_reading' => $row[2],
                'consumption' => $row[3],
                'reading_date' => '2026-05-18',
                'status' => MeterReading::STATUS_INVOICED,
                'image_path' => "/storage/demo/readings/{$key}-2026-05.jpg",
                'note' => 'Chỉ số mẫu kỳ 05/2026.',
                'created_by' => $admins['manager_sg'],
                ...$this->timestamps(),
            ]),
        ])->all();
    }

    private function seedInvoices(array $admins, array $contracts, array $rooms): array
    {
        $rows = [
            'sg_a101' => ['INV-2026-05-0001', $contracts['sg_a101'], $rooms['sg_a101'], 5720000, 2000000, Invoice::STATUS_PARTIALLY_PAID],
            'td_b201' => ['INV-2026-05-0002', $contracts['td_b201'], $rooms['td_b201'], 6118000, 6118000, Invoice::STATUS_PAID],
            'bc_c301' => ['INV-2026-05-0003', $contracts['bc_c301'], $rooms['bc_c301'], 8246000, 0, Invoice::STATUS_UNPAID],
        ];

        return collect($rows)->mapWithKeys(fn (array $row, string $key): array => [
            $key => $this->upsertAndGetId('invoices', ['invoice_code' => $row[0]], [
                'contract_id' => $row[1],
                'room_id' => $row[2],
                'billing_month' => 5,
                'billing_year' => 2026,
                'period_start' => '2026-05-01',
                'period_end' => '2026-05-31',
                'previous_debt_amount' => 0,
                'total_amount' => $row[3],
                'paid_amount' => $row[4],
                'remaining_amount' => $row[3] - $row[4],
                'due_date' => '2026-06-05',
                'status' => $row[5],
                'issued_at' => '2026-05-18 08:00:00',
                'created_by' => $admins['manager_sg'],
                ...$this->timestamps(),
            ]),
        ])->all();
    }

    private function seedInvoiceItems(array $invoices, array $readings, array $services): void
    {
        $rows = [
            [$invoices['sg_a101'], null, null, InvoiceItem::ITEM_TYPE_ROOM, 'Tiền phòng tháng 05/2026', 1, 4500000],
            [$invoices['sg_a101'], $services['electric'], $readings['sg_a101_electric'], InvoiceItem::ITEM_TYPE_ELECTRIC, 'Tiền điện 120 kWh', 120, 4000],
            [$invoices['sg_a101'], $services['water'], $readings['sg_a101_water'], InvoiceItem::ITEM_TYPE_WATER, 'Tiền nước 20 m³', 20, 18000],
            [$invoices['sg_a101'], $services['internet'], null, InvoiceItem::ITEM_TYPE_INTERNET, 'Internet tháng 05/2026', 1, 120000],
            [$invoices['sg_a101'], $services['trash'], null, InvoiceItem::ITEM_TYPE_TRASH, 'Phí rác 2 người', 2, 30000],
            [$invoices['sg_a101'], $services['parking'], null, InvoiceItem::ITEM_TYPE_PARKING, 'Phí gửi xe', 1, 150000],
            [$invoices['sg_a101'], $services['cleaning'], null, InvoiceItem::ITEM_TYPE_SURCHARGE, 'Vệ sinh khu vực chung', 1, 50000],
            [$invoices['td_b201'], null, null, InvoiceItem::ITEM_TYPE_ROOM, 'Tiền phòng tháng 05/2026', 1, 5200000],
            [$invoices['td_b201'], $services['electric'], $readings['td_b201_electric'], InvoiceItem::ITEM_TYPE_ELECTRIC, 'Tiền điện 90 kWh', 90, 4000],
            [$invoices['td_b201'], $services['water'], $readings['td_b201_water'], InvoiceItem::ITEM_TYPE_WATER, 'Tiền nước 12 m³', 12, 18000],
            [$invoices['td_b201'], $services['internet'], null, InvoiceItem::ITEM_TYPE_INTERNET, 'Internet tháng 05/2026', 1, 120000],
            [$invoices['td_b201'], $services['trash'], null, InvoiceItem::ITEM_TYPE_TRASH, 'Phí rác 1 người', 1, 30000],
            [$invoices['td_b201'], $services['parking'], null, InvoiceItem::ITEM_TYPE_PARKING, 'Phí gửi xe', 1, 150000],
            [$invoices['td_b201'], $services['cleaning'], null, InvoiceItem::ITEM_TYPE_SURCHARGE, 'Vệ sinh khu vực chung', 1, 42000],
            [$invoices['bc_c301'], null, null, InvoiceItem::ITEM_TYPE_ROOM, 'Tiền phòng tháng 05/2026', 1, 6800000],
            [$invoices['bc_c301'], $services['electric'], $readings['bc_c301_electric'], InvoiceItem::ITEM_TYPE_ELECTRIC, 'Tiền điện 160 kWh', 160, 4000],
            [$invoices['bc_c301'], $services['water'], $readings['bc_c301_water'], InvoiceItem::ITEM_TYPE_WATER, 'Tiền nước 22 m³', 22, 18000],
            [$invoices['bc_c301'], $services['internet'], null, InvoiceItem::ITEM_TYPE_INTERNET, 'Internet tháng 05/2026', 1, 120000],
            [$invoices['bc_c301'], $services['trash'], null, InvoiceItem::ITEM_TYPE_TRASH, 'Phí rác 2 người', 2, 30000],
            [$invoices['bc_c301'], $services['parking'], null, InvoiceItem::ITEM_TYPE_PARKING, 'Phí gửi xe điện', 1, 180000],
            [$invoices['bc_c301'], $services['cleaning'], null, InvoiceItem::ITEM_TYPE_SURCHARGE, 'Vệ sinh khu vực chung', 1, 50000],
        ];

        foreach ($rows as $row) {
            $this->updateOrInsert('invoice_items', [
                'invoice_id' => $row[0],
                'description' => $row[4],
            ], [
                'service_id' => $row[1],
                'meter_reading_id' => $row[2],
                'item_type' => $row[3],
                'quantity' => $row[5],
                'unit_price' => $row[6],
                'amount' => $row[5] * $row[6],
                ...$this->timestamps(),
            ]);
        }
    }

    private function seedPayments(array $admins, array $invoices): void
    {
        $rows = [
            ['PAY-2026-05-0001', $invoices['sg_a101'], 2000000, '2026-05-19 09:30:00', Payment::PAYMENT_METHOD_BANK_TRANSFER, 'VCB202605190001', Payment::STATUS_CONFIRMED],
            ['PAY-2026-05-0002', $invoices['td_b201'], 6118000, '2026-05-19 10:15:00', Payment::PAYMENT_METHOD_CASH, null, Payment::STATUS_CONFIRMED],
        ];

        foreach ($rows as $row) {
            $this->updateOrInsert('payments', ['payment_code' => $row[0]], [
                'invoice_id' => $row[1],
                'amount' => $row[2],
                'payment_date' => $row[3],
                'payment_method' => $row[4],
                'transaction_reference' => $row[5],
                'status' => $row[6],
                'proof_image' => $row[5] ? "/storage/demo/payments/{$row[0]}.jpg" : null,
                'note' => 'Thanh toán mẫu kỳ 05/2026.',
                'collected_by' => $admins['manager_sg'],
                ...$this->timestamps(),
            ]);
        }
    }

    private function seedMaintenanceRequests(array $admins, array $rooms, array $tenants): array
    {
        $rows = [
            'aircon' => ['MR-2026-0001', $tenants['an'], $rooms['sg_a101'], 'Máy lạnh chảy nước', 'Máy lạnh trong phòng bị chảy nước khi hoạt động lâu.', MaintenanceRequest::STATUS_COMPLETED, '2026-05-12 08:30:00', '2026-05-12 16:45:00'],
            'light' => ['MR-2026-0002', $tenants['duy'], $rooms['bc_c301'], 'Đèn nhà vệ sinh chập chờn', 'Đèn nhà vệ sinh lúc sáng lúc tắt, cần kiểm tra dây điện.', MaintenanceRequest::STATUS_PROCESSING, '2026-05-18 14:00:00', null],
        ];

        return collect($rows)->mapWithKeys(fn (array $row, string $key): array => [
            $key => $this->upsertAndGetId('maintenance_requests', ['request_code' => $row[0]], [
                'tenant_id' => $row[1],
                'room_id' => $row[2],
                'title' => $row[3],
                'description' => $row[4],
                'status' => $row[5],
                'images' => $this->json(["/storage/demo/maintenance/{$key}.jpg"]),
                'assigned_to' => $admins['tech_sg'],
                'received_at' => $row[6],
                'completed_at' => $row[7],
                ...$this->timestamps(),
            ]),
        ])->all();
    }

    private function seedMaintenanceFeedbacks(array $maintenanceRequests, array $tenants): void
    {
        $this->updateOrInsert('maintenance_feedbacks', [
            'maintenance_request_id' => $maintenanceRequests['aircon'],
            'tenant_id' => $tenants['an'],
        ], [
            'rating' => 5,
            'images' => $this->json(['/storage/demo/maintenance/feedback-aircon.jpg']),
            'comment' => 'Kỹ thuật xử lý nhanh, máy lạnh đã hoạt động bình thường.',
            ...$this->timestamps(),
        ]);
    }

    private function seedMaintenanceLogs(array $admins, array $maintenanceRequests): void
    {
        $rows = [
            [$maintenanceRequests['aircon'], null, MaintenanceRequest::STATUS_CREATED, 'Khách thuê tạo phiếu sửa chữa.'],
            [$maintenanceRequests['aircon'], MaintenanceRequest::STATUS_CREATED, MaintenanceRequest::STATUS_PROCESSING, 'Quản lý đã phân công và kỹ thuật bắt đầu kiểm tra.'],
            [$maintenanceRequests['aircon'], MaintenanceRequest::STATUS_PROCESSING, MaintenanceRequest::STATUS_COMPLETED, 'Đã vệ sinh đường thoát nước máy lạnh.'],
            [$maintenanceRequests['light'], null, MaintenanceRequest::STATUS_CREATED, 'Khách thuê tạo phiếu sửa chữa.'],
            [$maintenanceRequests['light'], MaintenanceRequest::STATUS_CREATED, MaintenanceRequest::STATUS_PROCESSING, 'Kỹ thuật đang xử lý.'],
        ];

        foreach ($rows as $row) {
            $this->updateOrInsert('maintenance_request_logs', [
                'maintenance_request_id' => $row[0],
                'new_status' => $row[2],
                'note' => $row[3],
            ], [
                'old_status' => $row[1],
                'created_by' => $admins['tech_sg'],
                'created_at' => $this->now,
            ]);
        }
    }

    private function seedNotifications(array $admins, array $buildings, array $rooms, array $tenants): array
    {
        $rows = [
            'invoice' => ['Thông báo phát hành hóa đơn tháng 05/2026', 'Hóa đơn tháng 05/2026 đã được phát hành, vui lòng thanh toán trước ngày 05/06/2026.', Notification::NOTIFICATION_TYPE_INVOICE, Notification::TARGET_TYPE_ALL, null, null, null, Notification::STATUS_SENT],
            'maintenance' => ['Bảo trì khu vực chung', 'Tòa Sài Gòn Central bảo trì thang máy từ 09:00 đến 11:00 ngày 20/05/2026.', Notification::NOTIFICATION_TYPE_MAINTENANCE, Notification::TARGET_TYPE_BUILDING, $buildings['sg_central'], null, null, Notification::STATUS_SENT],
            'tenant' => ['Nhắc lịch kiểm tra phòng', 'Quản lý sẽ kiểm tra tình trạng phòng C301 vào chiều 21/05/2026.', Notification::NOTIFICATION_TYPE_SYSTEM, Notification::TARGET_TYPE_TENANT, null, $rooms['bc_c301'], $tenants['duy'], Notification::STATUS_SENT],
        ];

        return collect($rows)->mapWithKeys(fn (array $row, string $key): array => [
            $key => $this->upsertAndGetId('notifications', ['title' => $row[0]], [
                'content' => $row[1],
                'notification_type' => $row[2],
                'target_type' => $row[3],
                'building_id' => $row[4],
                'room_id' => $row[5],
                'tenant_id' => $row[6],
                'published_at' => '2026-05-19 08:00:00',
                'status' => $row[7],
                'created_by' => $admins['manager_sg'],
                ...$this->timestamps(),
            ]),
        ])->all();
    }

    private function seedNotificationReads(array $notifications, array $tenants): void
    {
        foreach ([[$notifications['invoice'], $tenants['an']], [$notifications['invoice'], $tenants['chi']], [$notifications['tenant'], $tenants['duy']]] as $row) {
            $this->updateOrInsert('notification_reads', ['notification_id' => $row[0], 'tenant_id' => $row[1]], [
                'read_at' => '2026-05-19 09:00:00',
            ]);
        }
    }

    private function seedExpenseCategories(array $admins): array
    {
        $rows = [
            'repair' => ['Sửa chữa', 'Chi phí sửa chữa thiết bị, phòng và khu vực chung.'],
            'cleaning' => ['Vệ sinh', 'Chi phí vệ sinh định kỳ tòa nhà.'],
            'internet' => ['Internet', 'Chi phí đường truyền internet.'],
            'operation' => ['Vận hành chung', 'Chi phí vận hành khác của tòa nhà.'],
        ];

        return collect($rows)->mapWithKeys(fn (array $row, string $key): array => [
            $key => $this->upsertAndGetId('expense_categories', ['name' => $row[0]], [
                'description' => $row[1],
                'is_active' => ExpenseCategory::ACTIVE,
                'created_by' => $admins['super'],
                ...$this->timestamps(),
            ]),
        ])->all();
    }

    private function seedExpenses(array $admins, array $buildings, array $rooms, array $expenseCategories): void
    {
        $rows = [
            ['EXP-2026-0001', $buildings['sg_central'], $rooms['sg_a101'], $expenseCategories['repair'], 'Sửa máy lạnh phòng A101', 650000, '2026-05-12', Expense::PAYMENT_METHOD_CASH],
            ['EXP-2026-0002', $buildings['sg_central'], null, $expenseCategories['cleaning'], 'Vệ sinh khu vực chung tháng 05', 1200000, '2026-05-15', Expense::PAYMENT_METHOD_BANK_TRANSFER],
            ['EXP-2026-0003', $buildings['td_garden'], null, $expenseCategories['internet'], 'Thanh toán internet tòa Tân Định', 900000, '2026-05-10', Expense::PAYMENT_METHOD_BANK_TRANSFER],
            ['EXP-2026-0004', $buildings['bc_studio'], null, $expenseCategories['operation'], 'Mua bóng đèn dự phòng', 350000, '2026-05-18', Expense::PAYMENT_METHOD_CASH],
        ];

        foreach ($rows as $row) {
            $this->updateOrInsert('expenses', ['expense_code' => $row[0]], [
                'building_id' => $row[1],
                'room_id' => $row[2],
                'expense_category_id' => $row[3],
                'title' => $row[4],
                'amount' => $row[5],
                'expense_date' => $row[6],
                'receipt_images' => $this->json(["/storage/demo/expenses/{$row[0]}.jpg"]),
                'payment_method' => $row[7],
                'note' => 'Khoản chi vận hành mẫu.',
                'status' => Expense::STATUS_RECORDED,
                'created_by' => $admins['manager_sg'],
                ...$this->timestamps(),
            ]);
        }
    }

    private function seedSettings(array $admins, array $buildings): void
    {
        $rows = [
            [null, 'Số hotline hỗ trợ', '1900 6868', 'Hotline hiển thị cho khách thuê.', true],
            [null, 'Email hỗ trợ', 'support@stayhub.local', 'Email tiếp nhận hỗ trợ.', true],
            [$buildings['sg_central'], 'Giờ yên tĩnh', '22:00 - 06:00', 'Khung giờ hạn chế tiếng ồn.', true],
            [$buildings['td_garden'], 'Ngày thu tiền phòng', '05', 'Ngày chốt thanh toán hàng tháng.', true],
        ];

        foreach ($rows as $row) {
            $this->updateOrInsert('settings', ['building_id' => $row[0], 'setting_label' => $row[1]], [
                'setting_value' => $row[2],
                'description' => $row[3],
                'is_public' => $row[4] ? Setting::PUBLIC : Setting::PRIVATE,
                'created_by' => $admins['super'],
                ...$this->timestamps(),
            ]);
        }
    }

    private function seedAdminLogs(array $admins, array $buildings, array $rooms, array $tenants, array $contracts, array $invoices): void
    {
        $rows = [
            ['seed_create', 'building', $buildings['sg_central'], ['name' => 'StayHub Sài Gòn Central']],
            ['seed_create', 'room', $rooms['sg_a101'], ['room_number' => 'A101']],
            ['seed_create', 'tenant', $tenants['an'], ['full_name' => 'Lê Hoàng An']],
            ['seed_create', 'contract', $contracts['sg_a101'], ['contract_code' => 'HD-2026-0001']],
            ['seed_create', 'invoice', $invoices['sg_a101'], ['invoice_code' => 'INV-2026-05-0001']],
        ];

        foreach ($rows as $row) {
            $this->updateOrInsert('admin_logs', ['action' => $row[0], 'entity_type' => $row[1], 'entity_id' => $row[2]], [
                'admin_id' => $admins['super'],
                'old_data' => null,
                'new_data' => $this->json($row[3]),
                'ip_address' => '127.0.0.1',
                'user_agent' => 'StayHubDemoSeeder',
                'created_at' => $this->now,
            ]);
        }
    }

    private function seedExpandedDemoData(array $admins, array $regions, array $roomTypes, array $assets, array $services, array $expenseCategories): void
    {
        $buildings = $this->seedExpandedBuildings($admins, $regions);
        $this->seedBuildingImages($admins, $buildings);
        $this->seedServicePrices($buildings, $services);

        $rooms = $this->seedExpandedRooms($admins, $buildings, $roomTypes);
        $this->seedRoomImages($admins, $rooms);
        $this->seedExpandedRoomAssets($assets, $rooms);

        $tenants = $this->seedExpandedTenants($admins, $rooms, $buildings);
        $contracts = $this->seedExpandedContracts($admins, $rooms, $tenants);
        $this->seedExpandedContractTenants($admins, $contracts, $tenants);

        $vehicles = $this->seedExpandedVehicles($tenants);
        $this->seedExpandedContractVehicles($contracts, $vehicles);
        $this->seedExpandedContractDeposits($admins, $contracts);

        $meters = $this->seedExpandedMeterDevices($rooms, $services);
        $readings = $this->seedExpandedMeterReadings($admins, $meters);

        $invoices = $this->seedExpandedInvoices($admins, $contracts, $readings);
        $this->seedExpandedInvoiceItems($invoices, $readings, $services);
        $this->seedExpandedPayments($admins, $invoices);
        $this->seedExpandedMaintenance($admins, $rooms, $tenants);
        $this->seedExpandedNotifications($admins, $buildings, $rooms, $tenants);
        $this->seedExpandedExpenses($admins, $buildings, $rooms, $expenseCategories);
        $this->seedExpandedSettings($admins, $buildings);
    }

    private function seedExpandedBuildings(array $admins, array $regions): array
    {
        $rows = [
            'bt_tower' => ['HCM-BT-01', $regions['ben_thanh'], 'StayHub Bến Thành Tower', '102 Lê Lai, Phường Bến Thành, TP.HCM', 8, Building::GENDER_POLICY_MIXED],
            'col_river' => ['HCM-COL-01', $regions['cau_ong_lanh'], 'StayHub Cầu Ông Lãnh River', '44 Võ Văn Kiệt, Phường Cầu Ông Lãnh, TP.HCM', 6, Building::GENDER_POLICY_MALE],
            'sg_lux' => ['HCM-SG-02', $regions['sai_gon'], 'StayHub Sài Gòn Lux', '19 Đồng Khởi, Phường Sài Gòn, TP.HCM', 9, Building::GENDER_POLICY_MIXED],
            'td_home' => ['HCM-TD-02', $regions['tan_dinh'], 'StayHub Tân Định Home', '71 Trần Quang Khải, Phường Tân Định, TP.HCM', 5, Building::GENDER_POLICY_FEMALE],
            'bc_square' => ['HCM-BC-02', $regions['ban_co'], 'StayHub Bàn Cờ Square', '33 Nguyễn Đình Chiểu, Phường Bàn Cờ, TP.HCM', 7, Building::GENDER_POLICY_MIXED],
        ];

        return collect($rows)->mapWithKeys(fn (array $row, string $key): array => [
            $key => $this->upsertAndGetId('buildings', ['slug' => Str::slug($row[2])], [
                'region_id' => $row[1],
                'manager_admin_id' => $admins[$key],
                'name' => $row[2],
                'slug' => Str::slug($row[2]),
                'address' => $row[3],
                'total_floors' => $row[4],
                'gender_policy' => $row[5],
                'description' => 'Tòa nhà mở rộng để kiểm thử dữ liệu lớn StayHub.',
                'status' => Building::STATUS_ACTIVE,
                'created_by' => $admins['super'],
                ...$this->timestamps(),
            ]),
        ])->all();
    }

    private function seedExpandedRooms(array $admins, array $buildings, array $roomTypes): array
    {
        $rooms = [];
        $roomTypeKeys = ['standard', 'premium', 'studio'];

        foreach ($buildings as $buildingKey => $buildingId) {
            $prefix = strtoupper(Str::before($buildingKey, '_'));

            for ($floor = 1; $floor <= 3; $floor++) {
                for ($position = 1; $position <= 4; $position++) {
                    $roomNumber = $prefix . $floor . str_pad((string) $position, 2, '0', STR_PAD_LEFT);
                    $typeKey = $roomTypeKeys[($floor + $position) % count($roomTypeKeys)];
                    $basePrice = match ($typeKey) {
                        'premium' => 5200000 + ($floor * 150000) + ($position * 50000),
                        'studio' => 6500000 + ($floor * 180000) + ($position * 60000),
                        default => 3600000 + ($floor * 120000) + ($position * 40000),
                    };
                    $key = $buildingKey . '_' . strtolower($roomNumber);

                    $rooms[$key] = $this->upsertAndGetId('rooms', ['building_id' => $buildingId, 'slug' => Str::slug($roomNumber)], [
                        'room_type_id' => $roomTypes[$typeKey],
                        'room_number' => $roomNumber,
                        'floor' => $floor,
                        'area_m2' => 22 + ($floor * 2) + ($position * 1.5),
                        'base_price' => $basePrice,
                        'max_occupants' => $typeKey === 'premium' ? 3 : 2,
                        'current_occupants' => 0,
                        'status' => ($floor === 3 && $position === 4) ? Room::STATUS_MAINTENANCE : Room::STATUS_ACTIVE,
                        'description' => 'Phòng mở rộng có dữ liệu hợp đồng, công tơ, hóa đơn và tài sản.',
                        'created_by' => $admins['manager_sg'],
                        ...$this->timestamps(),
                    ]);
                }
            }
        }

        return $rooms;
    }

    private function seedExpandedRoomAssets(array $assets, array $rooms): void
    {
        foreach ($rooms as $index => $roomId) {
            foreach (['bed', 'wardrobe', 'aircon'] as $assetKey) {
                $this->updateOrInsert('room_assets', ['room_id' => $roomId, 'asset_template_id' => $assets[$assetKey]], [
                    'quantity' => $assetKey === 'bed' ? 2 : 1,
                    'price' => match ($assetKey) {
                        'aircon' => 8500000,
                        'wardrobe' => 2600000,
                        default => 3200000,
                    },
                    'note' => 'Tài sản bàn giao mặc định cho phòng mở rộng.',
                    ...$this->timestamps(),
                ]);
            }

            if (crc32((string) $index) % 2 === 0) {
                $this->updateOrInsert('room_assets', ['room_id' => $roomId, 'asset_template_id' => $assets['fridge']], [
                    'quantity' => 1,
                    'price' => 4300000,
                    'note' => 'Tủ lạnh mini.',
                    ...$this->timestamps(),
                ]);
            }
        }
    }

    private function seedExpandedTenants(array $admins, array $rooms, array $buildings): array
    {
        $buildingManagers = collect($buildings)->mapWithKeys(fn (int $buildingId, string $buildingKey): array => [
            $buildingId => $admins[$buildingKey],
        ]);
        $roomBuildings = DB::table('rooms')
            ->whereIn('id', array_values($rooms))
            ->pluck('building_id', 'id')
            ->all();
        $roomManagers = collect($roomBuildings)
            ->map(fn (int $buildingId): int => $buildingManagers[$buildingId])
            ->all();
        $roomIds = array_values($rooms);
        $lastNames = ['Nguyễn', 'Trần', 'Lê', 'Phạm', 'Hoàng', 'Vũ', 'Đặng', 'Bùi', 'Đỗ', 'Hồ'];
        $middleNames = ['Minh', 'Hoàng', 'Gia', 'Thanh', 'Ngọc', 'Quốc', 'Tuấn', 'Thảo'];
        $firstNames = ['Anh', 'Bảo', 'Châu', 'Dũng', 'Hà', 'Khang', 'Linh', 'Mai', 'Nam', 'Phúc', 'Quỳnh', 'Sơn', 'Trang', 'Uyên', 'Vy', 'Yến'];
        $tenants = [];

        for ($i = 1; $i <= 40; $i++) {
            $gender = $i % 2 === 0 ? Tenant::GENDER_FEMALE : Tenant::GENDER_MALE;
            $name = $lastNames[($i - 1) % count($lastNames)] . ' ' . $middleNames[($i - 1) % count($middleNames)] . ' ' . $firstNames[($i - 1) % count($firstNames)];
            $key = 'demo_tenant_' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);

            $roomIndex = $i <= 30 ? $i - 1 : $i - 31;

            $tenants[$key] = $this->upsertAndGetId('tenants', ['username' => $key], [
                'building_id' => $roomBuildings[$roomIds[$roomIndex]],
                'created_by' => $roomManagers[$roomIds[$roomIndex]],
                'full_name' => $name,
                'gender' => $gender,
                'date_of_birth' => CarbonImmutable::create(1995 + ($i % 10), (($i - 1) % 12) + 1, (($i - 1) % 25) + 1)->toDateString(),
                'phone' => '0922' . str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                'email' => $key . '@stayhub.local',
                'password' => Hash::make('12345678'),
                'permanent_address' => 'TP.HCM',
                'current_address' => 'Đang thuê tại dữ liệu mở rộng StayHub',
                'avatar_url' => "/storage/demo/tenants/expanded/{$key}.jpg",
                'status' => $i > 36 ? Tenant::STATUS_STOPPED_RENTING : Tenant::STATUS_RENTING,
                'identity_type' => $i % 7 === 0 ? 2 : 1,
                'identity_number' => '089' . str_pad((string) $i, 9, '0', STR_PAD_LEFT),
                'front_image_url' => "/storage/demo/identity/expanded/{$key}-front.jpg",
                'back_image_url' => "/storage/demo/identity/expanded/{$key}-back.jpg",
                ...$this->timestamps(),
            ]);
        }
        return $tenants;
    }

    private function seedExpandedContracts(array $admins, array $rooms, array $tenants): array
    {
        $contracts = [];
        $roomKeys = array_values(array_keys($rooms));
        $tenantKeys = array_values(array_keys($tenants));

        for ($i = 1; $i <= 30; $i++) {
            $roomKey = $roomKeys[$i - 1];
            $tenantKey = $tenantKeys[$i - 1];
            $room = DB::table('rooms')->where('id', $rooms[$roomKey])->first();
            $code = 'HD-2026-EX-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT);

            $contracts[$roomKey] = $this->upsertAndGetId('contracts', ['contract_code' => $code], [
                'room_id' => $rooms[$roomKey],
                'start_date' => '2026-05-01',
                'end_date' => '2027-04-30',
                'actual_end_date' => null,
                'billing_cycle_day' => 5,
                'room_price' => $room->base_price,
                'deposit_amount' => $room->base_price * 2,
                'status' => $i > 27 ? Contract::STATUS_EXPIRED : Contract::STATUS_ACTIVE,
                'contract_files' => $this->json(["/storage/demo/contracts/expanded/{$code}.pdf"]),
                'note' => 'Hợp đồng mở rộng dùng kiểm thử dữ liệu lớn.',
                'created_by' => $admins['manager_sg'],
                ...$this->timestamps(),
            ]);
        }

        return $contracts;
    }

    private function seedExpandedContractTenants(array $admins, array $contracts, array $tenants): void
    {
        $tenantIds = array_values($tenants);
        $offset = 30;

        foreach (array_values($contracts) as $index => $contractId) {
            $this->updateOrInsert('contract_tenants', ['contract_id' => $contractId, 'tenant_id' => $tenantIds[$index]], [
                'join_date' => '2026-05-01',
                'leave_date' => null,
                'billing_start_date' => '2026-05-01',
                'billing_end_date' => null,
                'is_staying' => ContractTenant::STAYING,
                'created_by' => $admins['manager_sg'],
                ...$this->timestamps(),
            ]);

            if ($index < 10) {
                $this->updateOrInsert('contract_tenants', ['contract_id' => $contractId, 'tenant_id' => $tenantIds[$offset + $index]], [
                    'join_date' => '2026-05-03',
                    'leave_date' => null,
                    'billing_start_date' => '2026-05-03',
                    'billing_end_date' => null,
                    'is_staying' => ContractTenant::STAYING,
                    'created_by' => $admins['manager_sg'],
                    ...$this->timestamps(),
                ]);
            }
        }
    }

    private function seedExpandedVehicles(array $tenants): array
    {
        $vehicles = [];
        $tenantIds = array_values($tenants);

        for ($i = 1; $i <= 28; $i++) {
            $plate = '59X' . ($i % 9 + 1) . '-' . str_pad((string) (20000 + $i), 5, '0', STR_PAD_LEFT);
            $vehicles[$i] = $this->upsertAndGetId('vehicles', ['license_plate' => $plate], [
                'tenant_id' => $tenantIds[$i - 1],
                'vehicle_type' => $i % 6 === 0 ? Vehicle::VEHICLE_TYPE_ELECTRIC : Vehicle::VEHICLE_TYPE_MOTORBIKE,
                'brand' => $i % 3 === 0 ? 'Yamaha' : 'Honda',
                'color' => ['Đen', 'Trắng', 'Đỏ', 'Xanh'][$i % 4],
                'is_active' => Vehicle::ACTIVE,
                ...$this->timestamps(),
            ]);
        }

        return $vehicles;
    }

    private function seedExpandedContractVehicles(array $contracts, array $vehicles): void
    {
        $contractIds = array_values($contracts);

        foreach ($vehicles as $index => $vehicleId) {
            if (! isset($contractIds[$index - 1])) {
                continue;
            }

            $this->updateOrInsert('contract_vehicles', ['contract_id' => $contractIds[$index - 1], 'vehicle_id' => $vehicleId], [
                'started_at' => '2026-05-01',
                'ended_at' => null,
                'billing_start_date' => '2026-05-01',
                'billing_end_date' => null,
                'monthly_fee' => $index % 6 === 0 ? 180000 : 150000,
                'charge_policy' => ContractVehicle::CHARGE_POLICY_MONTHLY,
                'is_active' => ContractVehicle::ACTIVE,
                ...$this->timestamps(),
            ]);
        }
    }

    private function seedExpandedContractDeposits(array $admins, array $contracts): void
    {
        foreach ($contracts as $contractId) {
            $deposit = DB::table('contracts')->where('id', $contractId)->value('deposit_amount');

            $this->updateOrInsert('contract_deposit_transactions', ['contract_id' => $contractId, 'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_COLLECT], [
                'amount' => $deposit,
                'transaction_date' => '2026-05-01',
                'payment_method' => ContractDepositTransaction::PAYMENT_METHOD_BANK_TRANSFER,
                'note' => 'Thu cọc hợp đồng mở rộng.',
                'created_by' => $admins['manager_sg'],
                'created_at' => $this->now,
            ]);
        }
    }

    private function seedExpandedMeterDevices(array $rooms, array $services): array
    {
        $meters = [];

        foreach ($rooms as $key => $roomId) {
            $upperKey = strtoupper(str_replace('_', '-', $key));

            $meters[$key . '_electric'] = $this->upsertAndGetId('meter_devices', ['room_id' => $roomId, 'service_id' => $services['electric']], [
                'meter_type' => MeterDevice::METER_TYPE_ELECTRIC,
                'initial_reading' => 700 + (crc32($key) % 500),
                'installed_at' => '2026-05-01',
                'replaced_by_meter_id' => null,
                'final_reading' => null,
                'status' => MeterDevice::STATUS_ACTIVE,
                'image_path' => "/storage/demo/meters/expanded/{$key}-electric.jpg",
                'note' => 'Công tơ điện phòng mở rộng.',
                ...$this->timestamps(),
            ]);

            $meters[$key . '_water'] = $this->upsertAndGetId('meter_devices', ['room_id' => $roomId, 'service_id' => $services['water']], [
                'meter_type' => MeterDevice::METER_TYPE_WATER,
                'initial_reading' => 150 + (crc32($key) % 120),
                'installed_at' => '2026-05-01',
                'replaced_by_meter_id' => null,
                'final_reading' => null,
                'status' => MeterDevice::STATUS_ACTIVE,
                'image_path' => "/storage/demo/meters/expanded/{$key}-water.jpg",
                'note' => 'Công tơ nước phòng mở rộng.',
                ...$this->timestamps(),
            ]);
        }

        return $meters;
    }

    private function seedExpandedMeterReadings(array $admins, array $meters): array
    {
        $readings = [];

        foreach ($meters as $key => $meterId) {
            $isElectric = str_ends_with($key, '_electric');
            $previous = DB::table('meter_devices')->where('id', $meterId)->value('initial_reading');
            $consumption = $isElectric ? 65 + (crc32($key) % 95) : 8 + (crc32($key) % 18);

            $readings[$key] = $this->upsertAndGetId('meter_readings', ['meter_device_id' => $meterId, 'billing_year' => 2026, 'billing_month' => 5], [
                'previous_reading' => $previous,
                'current_reading' => $previous + $consumption,
                'consumption' => $consumption,
                'reading_date' => '2026-05-18',
                'status' => MeterReading::STATUS_INVOICED,
                'image_path' => "/storage/demo/readings/expanded/{$key}-2026-05.jpg",
                'note' => 'Chỉ số mở rộng kỳ 05/2026.',
                'created_by' => $admins['manager_sg'],
                ...$this->timestamps(),
            ]);
        }

        return $readings;
    }

    private function seedExpandedInvoices(array $admins, array $contracts, array $readings): array
    {
        $invoices = [];

        foreach ($contracts as $roomKey => $contractId) {
            $contract = DB::table('contracts')->where('id', $contractId)->first();
            $tenantCount = DB::table('contract_tenants')->where('contract_id', $contractId)->count();
            $vehicleFee = DB::table('contract_vehicles')->where('contract_id', $contractId)->sum('monthly_fee');
            $electric = DB::table('meter_readings')->where('id', $readings[$roomKey . '_electric'])->value('consumption') * 4000;
            $water = DB::table('meter_readings')->where('id', $readings[$roomKey . '_water'])->value('consumption') * 18000;
            $total = $contract->room_price + $electric + $water + 120000 + ($tenantCount * 30000) + $vehicleFee + 50000;
            $paid = match (true) {
                $total < 5000000 => $total,
                ((int) $contractId) % 3 === 0 => 0,
                default => (int) round($total / 2, -3),
            };
            $code = 'INV-2026-05-EX-' . str_pad((string) $contractId, 5, '0', STR_PAD_LEFT);

            $invoices[$roomKey] = $this->upsertAndGetId('invoices', ['invoice_code' => $code], [
                'contract_id' => $contractId,
                'room_id' => $contract->room_id,
                'billing_month' => 5,
                'billing_year' => 2026,
                'period_start' => '2026-05-01',
                'period_end' => '2026-05-31',
                'previous_debt_amount' => 0,
                'total_amount' => $total,
                'paid_amount' => $paid,
                'remaining_amount' => $total - $paid,
                'due_date' => '2026-06-05',
                'status' => $paid <= 0 ? Invoice::STATUS_UNPAID : ($paid >= $total ? Invoice::STATUS_PAID : Invoice::STATUS_PARTIALLY_PAID),
                'issued_at' => '2026-05-18 08:00:00',
                'created_by' => $admins['manager_sg'],
                ...$this->timestamps(),
            ]);
        }

        return $invoices;
    }

    private function seedExpandedInvoiceItems(array $invoices, array $readings, array $services): void
    {
        foreach ($invoices as $roomKey => $invoiceId) {
            $invoice = DB::table('invoices')->where('id', $invoiceId)->first();
            $tenantCount = DB::table('contract_tenants')->where('contract_id', $invoice->contract_id)->count();
            $vehicleFee = DB::table('contract_vehicles')->where('contract_id', $invoice->contract_id)->sum('monthly_fee');
            $electricConsumption = DB::table('meter_readings')->where('id', $readings[$roomKey . '_electric'])->value('consumption');
            $waterConsumption = DB::table('meter_readings')->where('id', $readings[$roomKey . '_water'])->value('consumption');
            $items = [
                [null, null, InvoiceItem::ITEM_TYPE_ROOM, 'Tiền phòng tháng 05/2026', 1, $invoice->total_amount - ($electricConsumption * 4000) - ($waterConsumption * 18000) - 120000 - ($tenantCount * 30000) - $vehicleFee - 50000],
                [$services['electric'], $readings[$roomKey . '_electric'], InvoiceItem::ITEM_TYPE_ELECTRIC, "Tiền điện {$electricConsumption} kWh", $electricConsumption, 4000],
                [$services['water'], $readings[$roomKey . '_water'], InvoiceItem::ITEM_TYPE_WATER, "Tiền nước {$waterConsumption} m³", $waterConsumption, 18000],
                [$services['internet'], null, InvoiceItem::ITEM_TYPE_INTERNET, 'Internet tháng 05/2026', 1, 120000],
                [$services['trash'], null, InvoiceItem::ITEM_TYPE_TRASH, "Phí rác {$tenantCount} người", $tenantCount, 30000],
                [$services['parking'], null, InvoiceItem::ITEM_TYPE_PARKING, 'Phí gửi xe', 1, $vehicleFee],
                [$services['cleaning'], null, InvoiceItem::ITEM_TYPE_SURCHARGE, 'Vệ sinh khu vực chung', 1, 50000],
            ];

            foreach ($items as $item) {
                $this->updateOrInsert('invoice_items', ['invoice_id' => $invoiceId, 'description' => $item[3]], [
                    'service_id' => $item[0],
                    'meter_reading_id' => $item[1],
                    'item_type' => $item[2],
                    'quantity' => $item[4],
                    'unit_price' => $item[5],
                    'amount' => $item[4] * $item[5],
                    ...$this->timestamps(),
                ]);
            }
        }
    }

    private function seedExpandedPayments(array $admins, array $invoices): void
    {
        foreach ($invoices as $invoiceId) {
            $invoice = DB::table('invoices')->where('id', $invoiceId)->first();

            if ((float) $invoice->paid_amount <= 0) {
                continue;
            }

            $this->updateOrInsert('payments', ['payment_code' => 'PAY-EX-' . $invoice->invoice_code], [
                'invoice_id' => $invoiceId,
                'amount' => $invoice->paid_amount,
                'payment_date' => '2026-05-19 10:00:00',
                'payment_method' => Payment::PAYMENT_METHOD_BANK_TRANSFER,
                'transaction_reference' => 'EX' . str_pad((string) $invoiceId, 10, '0', STR_PAD_LEFT),
                'status' => Payment::STATUS_CONFIRMED,
                'proof_image' => "/storage/demo/payments/expanded/{$invoice->invoice_code}.jpg",
                'note' => 'Thanh toán mở rộng kỳ 05/2026.',
                'collected_by' => $admins['manager_sg'],
                ...$this->timestamps(),
            ]);
        }
    }

    private function seedExpandedMaintenance(array $admins, array $rooms, array $tenants): void
    {
        $roomIds = array_values($rooms);
        $tenantIds = array_values($tenants);
        $titles = ['Rò nước lavabo', 'Máy lạnh yếu', 'Ổ cắm điện lỏng', 'Khóa cửa kẹt', 'Đèn hành lang hỏng', 'Vòi sen yếu'];

        for ($i = 1; $i <= 12; $i++) {
            $code = 'MR-2026-EX-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT);
            $status = [MaintenanceRequest::STATUS_CREATED, MaintenanceRequest::STATUS_PROCESSING, MaintenanceRequest::STATUS_PROCESSING, MaintenanceRequest::STATUS_COMPLETED][$i % 4];
            $requestId = $this->upsertAndGetId('maintenance_requests', ['request_code' => $code], [
                'tenant_id' => $tenantIds[($i - 1) % count($tenantIds)],
                'room_id' => $roomIds[($i - 1) % count($roomIds)],
                'title' => $titles[($i - 1) % count($titles)],
                'description' => 'Phiếu sửa chữa mở rộng phục vụ kiểm thử danh sách và trạng thái.',
                'status' => $status,
                'images' => $this->json(["/storage/demo/maintenance/expanded/{$code}.jpg"]),
                'assigned_to' => $admins['tech_sg'],
                'received_at' => $status >= MaintenanceRequest::STATUS_PROCESSING ? '2026-05-18 09:00:00' : null,
                'completed_at' => $status === MaintenanceRequest::STATUS_COMPLETED ? '2026-05-19 15:30:00' : null,
                ...$this->timestamps(),
            ]);

            $this->updateOrInsert('maintenance_request_logs', ['maintenance_request_id' => $requestId, 'new_status' => $status, 'note' => 'Log trạng thái mở rộng.'], [
                'old_status' => null,
                'created_by' => $admins['tech_sg'],
                'created_at' => $this->now,
            ]);
        }
    }

    private function seedExpandedNotifications(array $admins, array $buildings, array $rooms, array $tenants): void
    {
        $buildingIds = array_values($buildings);
        $roomIds = array_values($rooms);
        $tenantIds = array_values($tenants);

        for ($i = 1; $i <= 10; $i++) {
            $type = [Notification::NOTIFICATION_TYPE_INVOICE, Notification::NOTIFICATION_TYPE_MAINTENANCE, Notification::NOTIFICATION_TYPE_SYSTEM, Notification::NOTIFICATION_TYPE_WARNING][$i % 4];
            $target = [Notification::TARGET_TYPE_ALL, Notification::TARGET_TYPE_BUILDING, Notification::TARGET_TYPE_ROOM, Notification::TARGET_TYPE_TENANT][$i % 4];
            $notificationId = $this->upsertAndGetId('notifications', ['title' => 'Thông báo mở rộng #' . $i], [
                'content' => 'Nội dung thông báo mở rộng dùng kiểm thử phân trang và lọc dữ liệu.',
                'notification_type' => $type,
                'target_type' => $target,
                'building_id' => $target === Notification::TARGET_TYPE_BUILDING ? $buildingIds[$i % count($buildingIds)] : null,
                'room_id' => $target === Notification::TARGET_TYPE_ROOM ? $roomIds[$i % count($roomIds)] : null,
                'tenant_id' => $target === Notification::TARGET_TYPE_TENANT ? $tenantIds[$i % count($tenantIds)] : null,
                'published_at' => '2026-05-19 08:00:00',
                'status' => Notification::STATUS_SENT,
                'created_by' => $admins['manager_sg'],
                ...$this->timestamps(),
            ]);

            foreach (array_slice($tenantIds, 0, min(8, count($tenantIds))) as $tenantId) {
                if (($tenantId + $i) % 2 === 0) {
                    $this->updateOrInsert('notification_reads', ['notification_id' => $notificationId, 'tenant_id' => $tenantId], [
                        'read_at' => '2026-05-19 09:00:00',
                    ]);
                }
            }
        }
    }

    private function seedExpandedExpenses(array $admins, array $buildings, array $rooms, array $expenseCategories): void
    {
        $buildingIds = array_values($buildings);
        $roomIds = array_values($rooms);
        $categoryIds = array_values($expenseCategories);
        $titles = ['Vệ sinh định kỳ', 'Sửa khóa phòng', 'Thay bóng đèn', 'Bảo trì máy bơm', 'Mua vật tư sửa chữa', 'Thanh toán internet'];

        for ($i = 1; $i <= 15; $i++) {
            $code = 'EXP-2026-EX-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT);

            $this->updateOrInsert('expenses', ['expense_code' => $code], [
                'building_id' => $buildingIds[($i - 1) % count($buildingIds)],
                'room_id' => $i % 3 === 0 ? $roomIds[($i - 1) % count($roomIds)] : null,
                'expense_category_id' => $categoryIds[($i - 1) % count($categoryIds)],
                'title' => $titles[($i - 1) % count($titles)],
                'amount' => 250000 + ($i * 85000),
                'expense_date' => '2026-05-' . str_pad((string) (5 + ($i % 20)), 2, '0', STR_PAD_LEFT),
                'receipt_images' => $this->json(["/storage/demo/expenses/expanded/{$code}.jpg"]),
                'payment_method' => $i % 2 === 0 ? Expense::PAYMENT_METHOD_BANK_TRANSFER : Expense::PAYMENT_METHOD_CASH,
                'note' => 'Khoản chi mở rộng phục vụ kiểm thử báo cáo.',
                'status' => Expense::STATUS_RECORDED,
                'created_by' => $admins['manager_sg'],
                ...$this->timestamps(),
            ]);
        }
    }

    private function seedExpandedSettings(array $admins, array $buildings): void
    {
        foreach ($buildings as $buildingId) {
            foreach ([['Giờ yên tĩnh', '22:00 - 06:00'], ['Quy định khách ra vào', 'Đăng ký với quản lý trước 22:00']] as $index => $setting) {
                $this->updateOrInsert('settings', ['building_id' => $buildingId, 'setting_label' => $setting[0]], [
                    'setting_value' => $setting[1],
                    'description' => 'Cấu hình riêng cho tòa mở rộng.',
                    'is_public' => Setting::PUBLIC,
                    'created_by' => $admins['super'],
                    ...$this->timestamps(),
                ]);
            }
        }
    }

    private function upsertAndGetId(string $table, array $unique, array $values): int
    {
        $this->updateOrInsert($table, $unique, $values);

        $query = DB::table($table);

        foreach ($unique as $column => $value) {
            $value === null ? $query->whereNull($column) : $query->where($column, $value);
        }

        return (int) $query->value('id');
    }

    private function updateOrInsert(string $table, array $unique, array $values): void
    {
        DB::table($table)->updateOrInsert($unique, $values);
    }

    private function timestamps(): array
    {
        return [
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ];
    }

    private function json(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
