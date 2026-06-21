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
use App\Models\MeterDevice;
use App\Models\MeterReading;
use App\Models\Room;
use App\Models\ServicePrice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;

class MeterReadingController extends Controller
{
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

            // 1. Fetch all rooms in this building with active contracts
            $rooms = Room::query()
                ->where('building_id', $buildingId)
                ->where('status', Room::STATUS_ACTIVE)
                ->with(['contracts' => function ($q) {
                    $q->where('status', 1); // Active contract
                }, 'contracts.tenants'])
                ->get();

            $targetDate = \Illuminate\Support\Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

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
                            'image_path' => $existingReading->image_path,
                            'image_url' => $existingReading->image_path ? ImageHelper::load($existingReading->image_path) : null,
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

            AdminActivityLogger::write($admin, 'analyze_meter_image', MeterReading::class, null, null, $analysis, $request);

            return ApiResponse::responseJson(
                (bool) ($analysis['success'] ?? false),
                $analysis['success'] ? 'AI đã phân tích ảnh đồng hồ' : 'Không thể đọc chỉ số từ ảnh, vui lòng nhập tay',
                200,
                new MeterImageAnalysisResource($analysis),
                200
            );

        } catch (\Exception $e) {
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

            $currentYear = now()->year;
            $currentMonth = now()->month;
            if ($year < $currentYear || ($year === $currentYear && $month < $currentMonth)) {
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

            $reading = DB::transaction(function () use ($validated, $previousReading, $consumption, $admin, $request) {
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

                AdminActivityLogger::write($admin, 'save_meter_reading', MeterReading::class, $record->id, null, $record->toArray(), $request);

                return $record;
            });

            return ApiResponse::responseJson(true, 'Chốt số đồng hồ thành công', 200, $reading, 200);

        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }

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
                    'max_tokens' => 80,
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

            return [
                'success' => true,
                'reading_value' => $readingValue,
                'confidence' => $confidence,
                'warning' => $payload['warning'] ?? null,
                'anomaly_warning' => $this->meterAnomalyWarning($readingValue, $previousReading),
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

    private function meterImagePrompt(int $meterType, ?float $previousReading): string
    {
        $meterName = $meterType === MeterDevice::METER_TYPE_WATER ? 'nước' : 'điện';
        $previousText = $previousReading !== null ? "\nChỉ số cũ để đối chiếu: {$previousReading}." : '';

        return <<<PROMPT
Bạn là hệ thống đọc chỉ số đồng hồ {$meterName} cho phòng trọ StayHub.
Hãy phân tích ảnh và CHỈ trả về JSON thuần, không markdown, không giải thích thêm.

Nếu đọc được chỉ số, trả đúng format:
{"value": <số nguyên>, "confidence": "high|medium|low", "warning": null, "meter_kind": "electric|water"}

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
- Nếu số đang lửng lơ giữa hai giá trị, làm tròn xuống theo ô số nguyên chính và đặt confidence là low.
- Nếu có nhiều đồng hồ, đọc đồng hồ to nhất/rõ nhất và đặt confidence là medium.
- Nếu mặt kính vỡ, số bị che một phần nhưng vẫn đoán được, trả value và confidence low.
{$previousText}
PROMPT;
    }

    private function parseAiJson(string $content): array
    {
        $content = trim($content);
        $content = preg_replace('/^```(?:json)?\s*/i', '', $content) ?? $content;
        $content = preg_replace('/\s*```$/', '', $content) ?? $content;

        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }

    private function normalizeAiError(string $error): string
    {
        return in_array($error, ['image_blurry', 'image_too_dark', 'image_glare', 'no_meter_found', 'meter_type_mismatch'], true)
            ? $error
            : 'invalid_response';
    }

    private function isExpectedMeterKind(mixed $meterKind, int $meterType): bool
    {
        if (! is_string($meterKind)) {
            return false;
        }

        $expectedKind = $meterType === MeterDevice::METER_TYPE_WATER ? 'water' : 'electric';

        return strtolower(trim($meterKind)) === $expectedKind;
    }

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
