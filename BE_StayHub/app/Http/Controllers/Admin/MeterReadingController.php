<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AdminActivityLogger;
use App\Helpers\AdminScope;
use App\Helpers\ApiResponse;
use App\Helpers\ImageHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MeterReading\AnalyzeImageRequest;
use App\Http\Requests\Admin\MeterReading\InitRequest;
use App\Http\Requests\Admin\MeterReading\StoreRequest;
use App\Http\Resources\Admin\MeterImageAnalysisResource;
use App\Models\Building;
use App\Models\Contract;
use App\Models\MeterDevice;
use App\Models\MeterReading;
use App\Models\Room;
use App\Models\RoomMovement;
use App\Models\ServicePrice;
use App\Support\BusinessRules\OperationalStateGuard;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;

class MeterReadingController extends Controller
{
    // Lấy thông tin khởi tạo trang chốt số điện nước
    public function init(InitRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

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

            $periodStart = Carbon::create($year, $month, 1)->startOfDay();
            $periodEnd = $periodStart->copy()->endOfMonth()->startOfDay();
            $targetDate = $periodEnd->toDateString();

            // 2. Get active service prices for electricity and water in this building
            $servicePrices = ServicePrice::query()
                ->where('building_id', $buildingId)
                ->whereIn('status', [ServicePrice::STATUS_ACTIVE, ServicePrice::STATUS_EXPIRED])
                ->whereDate('effective_from', '<=', $targetDate)
                ->where(function (Builder $query) use ($targetDate): void {
                    $query->whereNull('effective_to')
                        ->orWhereDate('effective_to', '>=', $targetDate);
                })
                ->whereHas('service', function ($q) {
                    $q->whereIn('slug', ['electric', 'water', 'dien-sinh-hoat', 'nuoc-sinh-hoat', 'dien', 'nuoc']);
                })
                ->with('service')
                ->orderByDesc('effective_from')
                ->get()
                ->unique('service_id')
                ->values()
                ->map(function ($price) {
                    return [
                        'service_id' => $price->service_id,
                        'name' => $price->service->name,
                        'slug' => $price->service->slug,
                        'price' => (float)$price->price,
                        'unit_name' => $price->service->unit_name,
                    ];
                });

            $transferFinalizations = $this->transferFinalizationContexts($buildingId, $periodStart, $periodEnd);
            $roomsData = $this->meterReadingRooms($buildingId, $month, $year, $transferFinalizations);

            return ApiResponse::responseJson(true, 'Dữ liệu chốt điện nước', 200, [
                'rooms' => $roomsData,
                'service_prices' => $servicePrices,
            ], 200);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }

    // Lấy danh sách phòng cần chốt số điện nước
    private function meterReadingRooms(int $buildingId, int $month, int $year, Collection $transferFinalizations): array
    {
        $rooms = Room::query()
            ->where('building_id', $buildingId)
            ->where('status', Room::STATUS_ACTIVE)
            ->with([
                'contracts' => fn ($query) => $query->where('status', Contract::STATUS_ACTIVE)->with('tenants'),
            ])
            ->orderBy('floor')
            ->orderBy('room_number')
            ->get();

        $roomsData = [];
        $includedContractIds = [];

        foreach ($rooms as $room) {
            $activeContract = $room->contracts->first();

            if (! $activeContract) {
                continue;
            }

            $context = $transferFinalizations->get((int) $activeContract->id);
            $roomsData[] = $this->roomReadingPayload($room, $activeContract, $month, $year, $context);
            $includedContractIds[] = (int) $activeContract->id;
        }

        $transferFinalizations
            ->reject(fn (array $context, int $contractId): bool => in_array($contractId, $includedContractIds, true))
            ->each(function (array $context) use (&$roomsData, $month, $year): void {
                $sourceContract = $context['source_contract'];
                $room = $sourceContract?->room;

                if (! $sourceContract || ! $room || $this->hasInvoiceForPeriod($sourceContract, $month, $year)) {
                    return;
                }

                $roomsData[] = $this->roomReadingPayload($room, $sourceContract, $month, $year, $context);
            });

        return $roomsData;
    }

    // Lấy bối cảnh chốt số phục vụ quyết toán chuyển phòng
    private function transferFinalizationContexts(int $buildingId, Carbon $periodStart, Carbon $periodEnd): Collection
    {
        return RoomMovement::query()
            ->with(['tenant:id,full_name', 'sourceContract.room.building', 'sourceContract.contractTenants.tenant'])
            ->where('movement_type', RoomMovement::MOVEMENT_TYPE_TRANSFER)
            ->whereIn('status', [RoomMovement::STATUS_PENDING, RoomMovement::STATUS_BLOCKED, RoomMovement::STATUS_EXECUTED])
            ->whereBetween('movement_date', [
                $periodStart->copy()->addDay()->toDateString(),
                $periodEnd->copy()->addDay()->toDateString(),
            ])
            ->whereNotNull('source_contract_id')
            ->whereHas('fromRoom', fn (Builder $query): Builder => $query->where('building_id', $buildingId))
            ->orderBy('movement_date')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (RoomMovement $movement): string => $movement->transfer_code ?: 'movement-'.$movement->id)
            ->map(fn (Collection $movements): ?array => $this->transferFinalizationContext($movements))
            ->filter()
            ->keyBy(fn (array $context): int => (int) $context['source_contract']->id);
    }

    // Lấy chi tiết bối cảnh chốt số chuyển phòng cụ thể
    private function transferFinalizationContext(Collection $movements): ?array
    {
        $firstMovement = $movements->first();
        $sourceContract = $firstMovement?->sourceContract;

        if (! $sourceContract) {
            return null;
        }

        $movingTenantIds = $movements
            ->pluck('tenant_id')
            ->map(fn ($tenantId): int => (int) $tenantId)
            ->unique()
            ->values();

        if ($movingTenantIds->isEmpty()) {
            return null;
        }

        $movementDate = Carbon::parse($firstMovement->movement_date)->startOfDay();
        $cutoffDate = $movementDate->copy()->subDay();

        return [
            'source_contract' => $sourceContract,
            'transfer_code' => $firstMovement->transfer_code,
            'movement_date' => $movementDate->toDateString(),
            'utility_cutoff_date' => $cutoffDate->toDateString(),
            'status' => (int) $firstMovement->status,
            'tenant_names' => $sourceContract->contractTenants
                ->map(fn ($row): ?string => $row->tenant?->full_name)
                ->filter()
                ->values()
                ->all(),
            'moving_tenant_names' => $movements->map(fn (RoomMovement $movement): ?string => $movement->tenant?->full_name)->filter()->values()->all(),
        ];
    }

    // Tạo cấu trúc dữ liệu số đọc của phòng
    private function roomReadingPayload(Room $room, Contract $contract, int $month, int $year, ?array $transferContext = null): array
    {
        $tenantName = $transferContext && ! empty($transferContext['tenant_names'])
            ? implode(', ', $transferContext['tenant_names'])
            : $this->contractTenantName($contract);

        return [
            'room_id' => $room->id,
            'room_number' => $room->room_number,
            'tenant_name' => $tenantName,
            'contract_id' => $contract->id,
            'contract_code' => $contract->contract_code,
            'contract_status' => (int) $contract->status,
            'is_transfer_finalization' => $transferContext !== null,
            'should_finalize_before_transfer' => $transferContext !== null,
            'transfer_code' => $transferContext['transfer_code'] ?? null,
            'movement_date' => $transferContext['movement_date'] ?? null,
            'utility_cutoff_date' => $transferContext['utility_cutoff_date'] ?? null,
            'cutoff_reason' => $transferContext
                ? 'Hợp đồng cũ sẽ được quyết toán trước khi chuyển phòng. Chốt điện/nước đến ngày trước ngày chuyển để lập hóa đơn cuối kỳ.'
                : null,
            'meters' => $this->metersForRoom($room, $month, $year),
        ];
    }

    // Lấy tên khách thuê đại diện trên hợp đồng
    private function contractTenantName(Contract $contract): ?string
    {
        $tenants = $contract->relationLoaded('tenants')
            ? $contract->tenants
            : $contract->tenants()->get();

        return $tenants->isNotEmpty() ? $tenants->first()->full_name : null;
    }

    // Danh sách công tơ điện nước trong phòng
    private function metersForRoom(Room $room, int $month, int $year): array
    {
        return MeterDevice::query()
            ->where('room_id', $room->id)
            ->where('status', MeterDevice::STATUS_ACTIVE)
            ->with('service')
            ->get()
            ->map(fn (MeterDevice $meter): array => $this->meterPayload($meter, $month, $year))
            ->values()
            ->all();
    }

    // Định dạng cấu trúc dữ liệu công tơ
    private function meterPayload(MeterDevice $meter, int $month, int $year): array
    {
        $existingReading = MeterReading::query()
            ->where('meter_device_id', $meter->id)
            ->where('billing_month', $month)
            ->where('billing_year', $year)
            ->first();

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

        $previousReading = $existingReading
            ? (float) $existingReading->previous_reading
            : ($previousReadingRecord ? (float) $previousReadingRecord->current_reading : (float) $meter->initial_reading);

        return [
            'id' => $meter->id,
            'meter_code' => $meter->meter_code,
            'meter_type' => $meter->meter_type,
            'service_id' => $meter->service_id,
            'service_name' => $meter->service?->name,
            'previous_reading' => $previousReading,
            'existing_reading' => $existingReading ? $this->existingReadingPayload($existingReading) : null,
        ];
    }

    // Định dạng cấu trúc dữ liệu số đọc cũ
    private function existingReadingPayload(MeterReading $reading): array
    {
        return [
            'id' => $reading->id,
            'current_reading' => (float) $reading->current_reading,
            'consumption' => (float) $reading->consumption,
            'reading_date' => $reading->reading_date ? $reading->reading_date->format('Y-m-d') : null,
            'status' => $reading->status,
            'image_path' => $reading->image_path,
            'image_url' => $reading->image_path ? ImageHelper::load($reading->image_path) : null,
            'note' => $reading->note,
        ];
    }

    // Kiểm tra phòng đã xuất hóa đơn cho kỳ này chưa
    private function hasInvoiceForPeriod(Contract $contract, int $month, int $year): bool
    {
        return $contract->invoices()
            ->where('billing_month', $month)
            ->where('billing_year', $year)
            ->where('status', '!=', \App\Models\Invoice::STATUS_CANCELLED)
            ->exists();
    }

    // Phân tích ảnh công tơ bằng AI
    public function analyzeImage(AnalyzeImageRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            $admin = $request->user('admin');
            if (!$admin) {
                return ApiResponse::responseJson(false, 'Unauthorized', 401, null, 401);
            }

            // Delete the previous temporary image (if the user retook the photo) to avoid
            // leaving orphaned files in public/upload/meter-readings/.
            if (!empty($validated['old_image_path'])) {
                $this->deleteMeterReadingTempImage($validated['old_image_path']);
            }

            $imagePath = ImageHelper::create($request->file('image'), 'meter-readings');
            $analysis = $this->analyzeMeterImage(
                $imagePath,
                (int) $validated['meter_type'],
                isset($validated['previous_reading']) ? (float) $validated['previous_reading'] : null
            );

            $analysis['image_path'] = $imagePath;
            $analysis['image_url']  = ImageHelper::load($imagePath);

            AdminActivityLogger::write($admin, 'Phân tích ảnh chỉ số điện nước', MeterReading::class, null, null, $analysis, $request);

            return ApiResponse::responseJson(
                (bool) ($analysis['success'] ?? false),
                $analysis['success'] ? 'AI đã phân tích ảnh đồng hồ' : 'Không thể đọc chỉ số từ ảnh, vui lòng nhập tay',
                200,
                new MeterImageAnalysisResource($analysis),
                200
            );

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }

    /**
     * Xóa ảnh tạm cũ trong thư mục meter-readings khi người dùng chụp lại.
     * Chỉ xóa khi đường dẫn thực sự nằm trong thư mục upload/meter-readings
     * để tránh xóa nhầm ảnh đã được lưu chính thức vào bản ghi meter_reading.
     */
    private function deleteMeterReadingTempImage(string $oldPath): void
    {
        $normalized = ltrim(str_replace('\\', '/', $oldPath), '/');

        // Bảo vệ: chỉ cho phép xóa file trong thư mục upload/meter-readings
        if (!str_starts_with($normalized, 'upload/meter-readings/')) {
            return;
        }

        // Bảo vệ thêm: không xóa nếu ảnh này đã được lưu vào bản ghi meter_reading
        $isLinked = MeterReading::where('image_path', $oldPath)
            ->orWhere('image_path', '/' . $normalized)
            ->exists();

        if ($isLinked) {
            return;
        }

        ImageHelper::delete($oldPath);
    }

    // Lưu chỉ số chốt điện nước mới
    public function store(StoreRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

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
            $existingReading = MeterReading::query()
                ->where('meter_device_id', $meter->id)
                ->where('billing_month', $month)
                ->where('billing_year', $year)
                ->first();
            $stateError = OperationalStateGuard::meterReadingBlockReason($meter, $existingReading);

            if ($stateError !== null) {
                return ApiResponse::responseJson(false, $stateError, 422, null, 422);
            }

            $currentYear = now()->year;
            $currentMonth = now()->month;
            if (($year < $currentYear || ($year === $currentYear && $month < $currentMonth)) && ! $this->isTransferCutoffReading($meter, $month, $year, $validated['reading_date'])) {
                return ApiResponse::responseJson(false, 'Không thể chốt chỉ số cho tháng cũ.', 422, null, 422);
            }

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

            $reading = DB::transaction(function () use ($validated, $previousReading, $consumption, $admin, $request, $meter) {
                $readingData = [
                    'previous_reading' => $previousReading,
                    'current_reading' => $validated['current_reading'],
                    'consumption' => $consumption,
                    'reading_date' => $validated['reading_date'],
                    'status' => MeterReading::STATUS_CONFIRMED,
                    'note' => $validated['note'] ?? null,
                    'created_by' => $admin->id,
                ];

                if (array_key_exists('image_path', $validated)) {
                    $readingData['image_path'] = $validated['image_path'];
                }

                $record = MeterReading::query()->updateOrCreate(
                    [
                        'meter_device_id' => $validated['meter_device_id'],
                        'billing_month' => $validated['billing_month'],
                        'billing_year' => $validated['billing_year'],
                    ],
                    $readingData
                );

                $meter->update([
                    'initial_reading' => $validated['current_reading']
                ]);

                AdminActivityLogger::write($admin, 'Lưu chỉ số điện nước', MeterReading::class, $record->id, null, $record->toArray(), $request);

                return $record;
            });

            return ApiResponse::responseJson(true, 'Chốt số đồng hồ thành công', 200, $reading, 200);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }

    // Kiểm tra có phải số chốt phục vụ bàn giao/chuyển phòng không
    private function isTransferCutoffReading(MeterDevice $meter, int $month, int $year, string $readingDate): bool
    {
        $readingDateCarbon = Carbon::parse($readingDate)->startOfDay();

        if ((int) $readingDateCarbon->month !== $month || (int) $readingDateCarbon->year !== $year) {
            return false;
        }

        return RoomMovement::query()
            ->where('from_room_id', $meter->room_id)
            ->where('movement_type', RoomMovement::MOVEMENT_TYPE_TRANSFER)
            ->whereIn('status', [RoomMovement::STATUS_PENDING, RoomMovement::STATUS_BLOCKED])
            ->whereDate('movement_date', $readingDateCarbon->copy()->addDay()->toDateString())
            ->exists();
    }

    // Gọi AI phân tích ảnh chụp mặt công tơ điện nước
    private function analyzeMeterImage(string $imagePath, int $meterType, ?float $previousReading = null): array
    {
        $absolutePath = ImageHelper::toAbsolutePath($imagePath);

        if (!$absolutePath || !is_file($absolutePath)) {
            return ['success' => false, 'error' => 'invalid_image'];
        }

        $apiKey = config('services.omniroute.api_key');
        $baseUrl = rtrim((string) config('services.omniroute.base_url'), '/');
        $model = config('services.omniroute.model');
        $timeout = max(15, (int) config('services.omniroute.timeout', 60));

        if (blank($apiKey) || blank($baseUrl) || blank($model)) {
            return ['success' => false, 'error' => 'ai_service_unavailable'];
        }

        $mimeType = mime_content_type($absolutePath) ?: 'image/jpeg';
        $base64Image = base64_encode((string) file_get_contents($absolutePath));
        $prompt = $this->meterImagePrompt($meterType, $previousReading);

        try {
            $response = Http::withToken((string) $apiKey)
                ->acceptJson()
                ->asJson()
                ->timeout($timeout)
                ->post($baseUrl . '/chat/completions', [
                    'model' => $model,
                    'max_tokens' => 180,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [[
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => $prompt],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => 'data:' . $mimeType . ';base64,' . $base64Image,
                                ],
                            ],
                        ],
                    ]],
                ]);

            if (!$response->successful()) {
                Log::warning('OmniRoute meter image analysis failed', [
                    'status' => $response->status(),
                    'model' => $model,
                    'body' => $response->body(),
                ]);

                return ['success' => false, 'error' => 'ai_service_unavailable'];
            }

            $content = $response->json('choices.0.message.content');
            if (!is_string($content) || trim($content) === '') {
                return ['success' => false, 'error' => 'invalid_response'];
            }

            $payload = $this->parseAiJson($content);

            if (isset($payload['error'])) {
                return ['success' => false, 'error' => $this->normalizeAiError((string) $payload['error'])];
            }

            if (! $this->isExpectedMeterKind($payload['meter_kind'] ?? null, $meterType)) {
                return ['success' => false, 'error' => 'meter_type_mismatch'];
            }

            if (!isset($payload['value']) || !is_numeric($payload['value'])) {
                return ['success' => false, 'error' => 'invalid_response'];
            }

            $readingValue = (int) floor((float) $payload['value']);
            $confidence = in_array($payload['confidence'] ?? null, ['high', 'medium', 'low'], true)
                ? $payload['confidence']
                : 'low';
            $uncertainDigits = $this->normalizeUncertainDigits(
                $payload['uncertain_digits'] ?? $payload['transition_digits'] ?? []
            );

            if ($uncertainDigits !== []) {
                $confidence = 'low';
            }

            return [
                'success' => true,
                'reading_value' => $readingValue,
                'confidence' => $confidence,
                'warning' => $payload['warning'] ?? null,
                'anomaly_warning' => $this->meterAnomalyWarning($readingValue, $previousReading),
                'uncertain_digits' => $uncertainDigits,
            ];
        } catch (JsonException) {
            return ['success' => false, 'error' => 'invalid_response'];
        } catch (\Throwable $e) {
            Log::warning('OmniRoute meter image analysis unavailable', [
                'model' => $model,
                'base_url' => $baseUrl,
                'timeout' => $timeout,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => 'ai_service_unavailable'];
        }
    }

    // Tạo câu lệnh prompt gửi AI phân tích ảnh công tơ
    private function meterImagePrompt(int $meterType, ?float $previousReading): string
    {
        $meterName = $meterType === MeterDevice::METER_TYPE_WATER ? 'nước' : 'điện';
        $previousText = $previousReading !== null ? "\nChỉ số cũ để đối chiếu: {$previousReading}." : '';

        return <<<PROMPT
Bạn là hệ thống đọc chỉ số đồng hồ {$meterName} cho phòng trọ StayHub.
Hãy phân tích ảnh và CHỈ trả về JSON thuần, không markdown, không giải thích thêm.

Nếu đọc được chỉ số, trả đúng format:
{"value": <số nguyên>, "confidence": "high|medium|low", "warning": null, "meter_kind": "electric|water", "uncertain_digits": []}

Nếu KHÔNG đọc được, trả đúng MỘT trong các lỗi:
{"error": "image_blurry"} khi ảnh mờ, rung, mất nét.
{"error": "image_too_dark"} khi ảnh quá tối.
{"error": "image_glare"} khi ảnh bị lóa sáng hoặc phản chiếu.
{"error": "no_meter_found"} khi không tìm thấy đồng hồ.
{"error": "meter_type_mismatch"} khi ảnh là đồng hồ điện nhưng đang yêu cầu đọc đồng hồ nước, hoặc ảnh là đồng hồ nước nhưng đang yêu cầu đọc đồng hồ điện.

Quy tắc đọc:
- Trước tiên phải phân loại loại đồng hồ trong ảnh: đồng hồ điện thường có chữ kWh, công tơ điện, pha/dây; đồng hồ nước thường có m³/m3, thân đồng hồ nước, mặt số nước hoặc kim nhỏ.
- Yêu cầu hiện tại là đọc đồng hồ {$meterName}. Nếu loại đồng hồ trong ảnh không đúng yêu cầu, KHÔNG đọc số, phải trả {"error": "meter_type_mismatch"}.
- Với đồng hồ điện cơ kWh: chỉ lấy dãy số nguyên chính trong các ô nền đen/trắng ở bên trái chữ kWh.
- Bỏ hoàn toàn ô số nhỏ màu đỏ, ô ngoài cùng bên phải, hoặc phần ghi /10, 1/10, 0.1 kWh; KHÔNG dùng nó để làm hàng đơn vị/chục.
- Ví dụ nếu dãy chính là 00480 và ô đỏ/phần lẻ là 7 thì value phải là 480, không phải 487 hoặc 490.
- Nếu có 5 ô số nguyên chính, trả về đúng số tạo bởi 5 ô đó sau khi bỏ các số 0 ở đầu.
- Với đồng hồ nước: chỉ đọc khi ảnh thật sự là đồng hồ nước, thường có đơn vị m³/m3; nếu thấy chữ kWh thì chắc chắn là sai loại.
- Với đồng hồ nước dạng ô số, chỉ lấy dãy số nguyên chính; bỏ kim nhỏ màu đỏ, vòng số đỏ, hoặc phần thập phân/lẻ.
- Nếu một ô số đang lửng lơ/chuyển bánh răng giữa hai giá trị, lấy chữ số thấp hơn đã hoàn thành để tạo value, đặt confidence là low.
- Khi có ô số đang lửng lơ/chuyển bánh răng, điền uncertain_digits theo format {"position": <vị trí từ trái sang phải, bắt đầu từ 1>, "lower_digit": <số thấp hơn>, "upper_digit": <số kế tiếp>, "chosen_digit": <số đã lấy>, "note": "Số đang chuyển từ X sang Y"}.
- Nếu không có ô số lửng lơ/chuyển bánh răng, uncertain_digits phải là [].
- Nếu có nhiều đồng hồ, đọc đồng hồ to nhất/rõ nhất và đặt confidence là medium.
- Nếu mặt kính vỡ, số bị che một phần nhưng vẫn đoán được, trả value và confidence low.
{$previousText}
PROMPT;
    }

    // Phân tích kết quả JSON trả về từ AI
    private function parseAiJson(string $content): array
    {
        $content = trim($content);
        $content = preg_replace('/^```(?:json)?\s*/i', '', $content) ?? $content;
        $content = preg_replace('/\s*```$/', '', $content) ?? $content;

        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }

    // Chuẩn hóa thông báo lỗi phân tích ảnh từ AI
    private function normalizeAiError(string $error): string
    {
        return in_array($error, ['image_blurry', 'image_too_dark', 'image_glare', 'no_meter_found', 'meter_type_mismatch'], true)
            ? $error
            : 'invalid_response';
    }

    // Kiểm tra loại công tơ phân tích được có đúng yêu cầu không
    private function isExpectedMeterKind(mixed $meterKind, int $meterType): bool
    {
        if (! is_string($meterKind)) {
            return false;
        }

        $expectedKind = $meterType === MeterDevice::METER_TYPE_WATER ? 'water' : 'electric';

        return strtolower(trim($meterKind)) === $expectedKind;
    }

    // Chuẩn hóa các chữ số không rõ ràng
    private function normalizeUncertainDigits(mixed $uncertainDigits): array
    {
        if (! is_array($uncertainDigits)) {
            return [];
        }

        return collect($uncertainDigits)
            ->filter(fn (mixed $digit): bool => is_array($digit))
            ->map(function (array $digit): array {
                $note = isset($digit['note']) && is_string($digit['note'])
                    ? mb_substr(trim($digit['note']), 0, 120)
                    : null;

                return [
                    'position' => $this->normalizeDigitInteger($digit['position'] ?? null, 1),
                    'lower_digit' => $this->normalizeDigitInteger($digit['lower_digit'] ?? $digit['from'] ?? null, 0, 9),
                    'upper_digit' => $this->normalizeDigitInteger($digit['upper_digit'] ?? $digit['to'] ?? null, 0, 9),
                    'chosen_digit' => $this->normalizeDigitInteger($digit['chosen_digit'] ?? $digit['chosen'] ?? null, 0, 9),
                    'note' => $note !== '' ? $note : null,
                ];
            })
            ->take(8)
            ->values()
            ->all();
    }

    // Chuẩn hóa phần số nguyên của chỉ số
    private function normalizeDigitInteger(mixed $value, ?int $minimum = null, ?int $maximum = null): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $integerValue = (int) $value;

        if ($minimum !== null && $integerValue < $minimum) {
            return $minimum;
        }

        if ($maximum !== null && $integerValue > $maximum) {
            return $maximum;
        }

        return $integerValue;
    }

    // Cảnh báo chỉ số điện nước tăng giảm bất thường
    private function meterAnomalyWarning(int $readingValue, ?float $previousReading): ?string
    {
        if ($previousReading === null) {
            return null;
        }

        if ($readingValue < $previousReading) {
            return "Chỉ số AI đọc ({$readingValue}) nhỏ hơn chỉ số cũ ({$previousReading}), vui lòng kiểm tra";
        }

        $consumption = $readingValue - $previousReading;
        $baseline = max($previousReading * 0.3, 100);

        if ($consumption > $baseline * 3) {
            return 'Lượng tiêu thụ bất thường cao, vui lòng xác nhận';
        }

        return null;
    }
}
