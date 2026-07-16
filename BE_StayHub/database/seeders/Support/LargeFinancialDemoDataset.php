<?php

namespace Database\Seeders\Support;

use Carbon\CarbonImmutable;

final class LargeFinancialDemoDataset
{
    public const PREFIX = 'SHOWCASE26';

    public const BUILDING_COUNT = 12;

    public const ROOMS_PER_BUILDING = 10;

    public const TENANTS_PER_ROOM = 20;

    public const BUILDING_REGION_CODES = [
        'HCM',
        'HCM-SG',
        'HCM',
        'HCM',
        'HCM',
        'HCM',
        'HCM-TD',
        'HCM',
        'HCM',
        'HCM',
        'HCM',
        'HCM',
    ];

    public function buildings(): array
    {
        return [
            $this->building('Ký túc xá Hoa Phượng Đỏ', '12 Nguyễn Văn Bảo, Phường Hạnh Thông, TP.HCM', 'Nguyễn Minh Quân', 1, 1),
            $this->building('Ký túc xá Bến Nghé', '45 Nguyễn Siêu, Phường Sài Gòn, TP.HCM', 'Trần Thu Hà', 2, 2),
            $this->building('Ký túc xá Gia Định', '88 Phan Đăng Lưu, Phường Gia Định, TP.HCM', 'Lê Hoàng Nam', 1, 3),
            $this->building('Ký túc xá Thủ Thiêm', '30 Trần Não, Phường An Khánh, TP.HCM', 'Phạm Ngọc Anh', 2, 4),
            $this->building('Ký túc xá Bình Quới', '156 Bình Quới, Phường Bình Quới, TP.HCM', 'Võ Đức Thành', 1, 5),
            $this->building('Ký túc xá Tân Cảng', '22 Điện Biên Phủ, Phường Thạnh Mỹ Tây, TP.HCM', 'Đặng Bích Ngọc', 2, 6),
            $this->building('Ký túc xá Hoàng Sa', '274 Hoàng Sa, Phường Tân Định, TP.HCM', 'Bùi Quốc Huy', 1, 7),
            $this->building('Ký túc xá Trường Sa', '319 Trường Sa, Phường Cầu Kiệu, TP.HCM', 'Đỗ Thanh Mai', 2, 8),
            $this->building('Ký túc xá Phú Nhuận', '51 Hoa Lan, Phường Cầu Kiệu, TP.HCM', 'Hồ Minh Khôi', 1, 9),
            $this->building('Ký túc xá Chợ Lớn', '108 Châu Văn Liêm, Phường Chợ Lớn, TP.HCM', 'Ngô Thùy Dương', 2, 10),
            $this->building('Ký túc xá An Đông', '63 An Dương Vương, Phường An Đông, TP.HCM', 'Dương Anh Tuấn', 1, 11),
            $this->building('Ký túc xá Văn Thánh', '17 Điện Biên Phủ, Phường Thạnh Mỹ Tây, TP.HCM', 'Mai Kim Oanh', 2, 12),
        ];
    }

    public function periods(): array
    {
        $periods = [];

        for ($period = CarbonImmutable::create(2025, 1, 1); $period->lessThanOrEqualTo(CarbonImmutable::create(2026, 6, 1)); $period = $period->addMonth()) {
            $periods[] = $period;
        }

        return $periods;
    }

    public function roomNumber(int $buildingNumber, int $roomPosition): int
    {
        $floor = intdiv($roomPosition - 1, 2) + 1;
        $roomOnFloor = (($roomPosition - 1) % 2) + 1;

        return ($floor * 100) + $roomOnFloor;
    }

    public function tenantUsername(int $buildingNumber, int $roomNumber, int $tenantPosition): string
    {
        return sprintf('showcase26_b%02d_p%d_t%02d', $buildingNumber, $roomNumber, $tenantPosition);
    }

    public function tenantEmail(int $buildingNumber, int $roomNumber, int $tenantPosition): string
    {
        return sprintf('showcase26.b%02d.p%d.t%02d@demo.example.test', $buildingNumber, $roomNumber, $tenantPosition);
    }

    public function tenantPhone(int $globalTenantNumber): string
    {
        return sprintf('098%07d', $globalTenantNumber);
    }

    public function tenantIdentityNumber(int $globalTenantNumber): string
    {
        return sprintf('0792%08d', $globalTenantNumber);
    }

    public function tenantName(int $globalTenantNumber): string
    {
        $familyNames = ['Nguyễn', 'Trần', 'Lê', 'Phạm', 'Hoàng', 'Huỳnh', 'Phan', 'Vũ', 'Võ', 'Đặng', 'Bùi', 'Đỗ'];
        $middleNames = ['Văn', 'Thị', 'Minh', 'Ngọc', 'Thanh', 'Hoài', 'Quốc', 'Phương'];
        $givenNames = ['An', 'Bình', 'Châu', 'Dũng', 'Giang', 'Hà', 'Hải', 'Hạnh', 'Huy', 'Lan', 'Linh', 'Mai', 'Nam', 'Nga', 'Phúc', 'Quân', 'Thảo', 'Trang', 'Tuấn', 'Vy'];
        $index = $globalTenantNumber - 1;

        return sprintf(
            '%s %s %s',
            $familyNames[$index % count($familyNames)],
            $middleNames[intdiv($index, count($familyNames)) % count($middleNames)],
            $givenNames[intdiv($index, count($familyNames) * count($middleNames)) % count($givenNames)],
        );
    }

    public function roomPrice(int $buildingNumber, int $roomPosition): int
    {
        $floor = intdiv($roomPosition - 1, 2) + 1;

        return 3_000_000 + (($buildingNumber - 1) * 100_000) + (($floor - 1) * 50_000);
    }

    public function electricConsumption(int $buildingNumber, int $roomNumber, CarbonImmutable $period): int
    {
        $seasonalVariation = [24, 30, 38, 48, 58, 64, 61, 55, 47, 39, 31, 26][$period->month - 1];

        return 220 + ($buildingNumber * 7) + (($roomNumber % 100) * 9) + $seasonalVariation + (($period->year - 2025) * 5);
    }

    public function waterConsumption(int $buildingNumber, int $roomNumber, CarbonImmutable $period): int
    {
        return 42 + ($buildingNumber % 5) + (($roomNumber % 100) * 2) + ($period->month % 4) + (($period->year - 2025) * 2);
    }

    public function paymentScenario(int $buildingNumber, int $roomNumber, CarbonImmutable $period): string
    {
        $seed = ($buildingNumber * 31) + ($roomNumber * 7) + ($period->year * 13) + $period->month;

        if ($period->lessThan(CarbonImmutable::create(2026, 5, 1))) {
            return match ($seed % 25) {
                0 => 'paid_split',
                1 => 'paid_late',
                default => 'paid',
            };
        }

        $floor = intdiv($roomNumber, 100);
        $roomPosition = (($floor - 1) * 2) + ($roomNumber % 100);
        $recentSeed = ((($buildingNumber - 1) * self::ROOMS_PER_BUILDING) + $roomPosition - 1 + $period->month) % 120;

        return match (true) {
            $recentSeed <= 2 => 'cancelled',
            $recentSeed <= 9 => 'unpaid',
            $recentSeed <= 19 => 'partial',
            $recentSeed <= 29 => 'paid_late',
            $recentSeed <= 41 => 'paid_split',
            default => 'paid',
        };
    }

    private function building(
        string $name,
        string $address,
        string $managerName,
        int $managerGender,
        int $buildingNumber,
    ): array {
        return [
            'name' => $name,
            'address' => $address,
            'region_code' => self::BUILDING_REGION_CODES[$buildingNumber - 1],
            'manager' => [
                'full_name' => $managerName,
                'gender' => $managerGender,
            ],
        ];
    }
}
