<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AdminActivityLogger;
use App\Helpers\AdminScope;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\MeterDevice;
use App\Models\MeterReading;
use App\Models\Room;
use App\Models\ServicePrice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MeterReadingController extends Controller
{
    public function init(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'building_id' => 'required|integer|exists:buildings,id',
                'billing_month' => 'required|integer|min:1|max:12',
                'billing_year' => 'required|integer|min:2020|max:2100',
            ]);

            $admin = $request->user('admin');
            if (!$admin) {
                return ApiResponse::responseJson(false, 'Unauthorized', 401, null, 401);
            }

            $buildingId = $validated['building_id'];
            $month = $validated['billing_month'];
            $year = $validated['billing_year'];

            // Check access scope
            if (AdminScope::isBuildingManager($admin)) {
                $building = Building::query()
                    ->whereKey($buildingId)
                    ->where('manager_admin_id', $admin->id)
                    ->first();
                if (!$building) {
                    return ApiResponse::responseJson(false, 'Bạn không có quyền quản lý tòa nhà này', 403, null, 403);
                }
            }

            // 1. Fetch all rooms in this building with active contracts
            $rooms = Room::query()
                ->where('building_id', $buildingId)
                ->where('status', Room::STATUS_ACTIVE)
                ->with(['contracts' => function ($q) {
                    $q->where('status', 1); // Active contract
                }, 'contracts.tenants'])
                ->get();

            // 2. Get active service prices for electricity and water in this building
            $servicePrices = ServicePrice::query()
                ->where('building_id', $buildingId)
                ->where('status', ServicePrice::STATUS_ACTIVE)
                ->whereHas('service', function ($q) {
                    $q->whereIn('slug', ['electric', 'water', 'dien-sinh-hoat', 'nuoc-sinh-hoat', 'dien', 'nuoc']);
                })
                ->with('service')
                ->get()
                ->map(function ($price) {
                    return [
                        'service_id' => $price->service_id,
                        'name' => $price->service->name,
                        'slug' => $price->service->slug,
                        'price' => (float)$price->price,
                        'unit_name' => $price->service->unit_name,
                    ];
                });

            // 3. For each room, load its meter devices and find:
            // - The latest reading BEFORE or AT the current target month/year
            // - Any existing reading for the target month/year
            $roomsData = [];
            foreach ($rooms as $room) {
                $activeContract = $room->contracts->first();
                $tenantName = $activeContract && $activeContract->tenants->isNotEmpty()
                    ? $activeContract->tenants->first()->full_name
                    : null;

                $meters = MeterDevice::query()
                    ->where('room_id', $room->id)
                    ->whereIn('status', [MeterDevice::STATUS_ACTIVE, MeterDevice::STATUS_INACTIVE])
                    ->with('service')
                    ->get();

                $metersData = [];
                foreach ($meters as $meter) {
                    // Check if there is an existing reading for this month/year
                    $existingReading = MeterReading::query()
                        ->where('meter_device_id', $meter->id)
                        ->where('billing_month', $month)
                        ->where('billing_year', $year)
                        ->first();

                    // Find previous reading (latest reading before target month/year)
                    $previousReadingRecord = MeterReading::query()
                        ->where('meter_device_id', $meter->id)
                        ->where(function ($query) use ($year, $month) {
                            $query->where('billing_year', '<', $year)
                                ->orWhere(function ($q) use ($year, $month) {
                                    $q->where('billing_year', $year)
                                        ->where('billing_month', '<', $month);
                                });
                        })
                        ->orderByDesc('billing_year')
                        ->orderByDesc('billing_month')
                        ->first();

                    $previousReading = $previousReadingRecord
                        ? (float)$previousReadingRecord->current_reading
                        : (float)$meter->initial_reading;

                    $metersData[] = [
                        'id' => $meter->id,
                        'meter_code' => $meter->meter_code,
                        'meter_type' => $meter->meter_type,
                        'service_id' => $meter->service_id,
                        'service_name' => $meter->service->name,
                        'previous_reading' => $previousReading,
                        'existing_reading' => $existingReading ? [
                            'id' => $existingReading->id,
                            'current_reading' => (float)$existingReading->current_reading,
                            'consumption' => (float)$existingReading->consumption,
                            'reading_date' => $existingReading->reading_date ? $existingReading->reading_date->format('Y-m-d') : null,
                            'status' => $existingReading->status,
                            'note' => $existingReading->note,
                        ] : null,
                    ];
                }

                $roomsData[] = [
                    'room_id' => $room->id,
                    'room_number' => $room->room_number,
                    'tenant_name' => $tenantName,
                    'contract_id' => $activeContract ? $activeContract->id : null,
                    'meters' => $metersData,
                ];
            }

            return ApiResponse::responseJson(true, 'Dữ liệu chốt điện nước', 200, [
                'rooms' => $roomsData,
                'service_prices' => $servicePrices,
            ], 200);

        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'meter_device_id' => 'required|integer|exists:meter_devices,id',
                'billing_month' => 'required|integer|min:1|max:12',
                'billing_year' => 'required|integer|min:2020|max:2100',
                'current_reading' => 'required|numeric|min:0',
                'reading_date' => 'required|date',
                'note' => 'nullable|string|max:500',
            ]);

            $admin = $request->user('admin');
            if (!$admin) {
                return ApiResponse::responseJson(false, 'Unauthorized', 401, null, 401);
            }

            $meter = MeterDevice::query()->findOrFail($validated['meter_device_id']);

            // Access control check
            if (AdminScope::isBuildingManager($admin)) {
                $hasAccess = Room::query()
                    ->whereKey($meter->room_id)
                    ->whereHas('building', fn (Builder $query) => $query->where('manager_admin_id', $admin->id))
                    ->exists();

                if (!$hasAccess) {
                    return ApiResponse::responseJson(false, 'Bạn không có quyền quản lý thiết bị này', 403, null, 403);
                }
            }

            $month = $validated['billing_month'];
            $year = $validated['billing_year'];
            $currentReading = $validated['current_reading'];

            // Find previous reading (latest before this target month/year)
            $previousReadingRecord = MeterReading::query()
                ->where('meter_device_id', $meter->id)
                ->where(function ($query) use ($year, $month) {
                    $query->where('billing_year', '<', $year)
                        ->orWhere(function ($q) use ($year, $month) {
                            $q->where('billing_year', $year)
                                ->where('billing_month', '<', $month);
                        });
                })
                ->orderByDesc('billing_year')
                ->orderByDesc('billing_month')
                ->first();

            $previousReading = $previousReadingRecord
                ? (float)$previousReadingRecord->current_reading
                : (float)$meter->initial_reading;

            if ($currentReading < $previousReading) {
                return ApiResponse::responseJson(
                    false,
                    "Chỉ số mới ({$currentReading}) không được nhỏ hơn chỉ số cũ ({$previousReading})",
                    422,
                    null,
                    422
                );
            }

            $consumption = $currentReading - $previousReading;

            $reading = DB::transaction(function () use ($validated, $previousReading, $consumption, $admin, $request) {
                $record = MeterReading::query()->updateOrCreate(
                    [
                        'meter_device_id' => $validated['meter_device_id'],
                        'billing_month' => $validated['billing_month'],
                        'billing_year' => $validated['billing_year'],
                    ],
                    [
                        'previous_reading' => $previousReading,
                        'current_reading' => $validated['current_reading'],
                        'consumption' => $consumption,
                        'reading_date' => $validated['reading_date'],
                        'status' => MeterReading::STATUS_CONFIRMED,
                        'note' => $validated['note'] ?? null,
                        'created_by' => $admin->id,
                    ]
                );

                AdminActivityLogger::write($admin, 'save_meter_reading', MeterReading::class, $record->id, null, $record->toArray(), $request);

                return $record;
            });

            return ApiResponse::responseJson(true, 'Chốt số đồng hồ thành công', 200, $reading, 200);

        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }
}
