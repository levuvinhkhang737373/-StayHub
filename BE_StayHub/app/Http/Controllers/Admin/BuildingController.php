<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AdminActivityLogger;
use App\Helpers\AdminScope;
use App\Helpers\ApiResponse;
use App\Helpers\ImageHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Building\IndexRequest;
use App\Http\Requests\Admin\Building\RegisterRequest;
use App\Http\Requests\Admin\Building\StatusRequest;
use App\Http\Requests\Admin\Building\UpdateRequest;
use App\Http\Resources\Admin\BuildingDetailResource;
use App\Http\Resources\Admin\BuildingResource;
use App\Models\Admin;
use App\Models\Building;
use App\Models\BuildingImage;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\Setting;
use App\Models\Contract;
use App\Models\ContractTenant;
use App\Models\Notification;
use App\Events\NotificationSent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Octane\Contracts\DispatchesTasks;
use Laravel\Octane\Facades\Octane;
use Swoole\Http\Server as SwooleServer;
use Throwable;

class BuildingController extends Controller
{
    public function index(IndexRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $admin = $request->user('admin');

            if (! $admin || (! AdminScope::isSuperAdmin($admin) && ! AdminScope::isBuildingManager($admin))) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xem tòa nhà', 403, null, 403);
            }

            $keyword = trim($validated['keyword'] ?? '');
            $buildings = $keyword !== ''
                ? $this->searchBuildings($keyword, $validated, $admin)
                : $this->queryBuildings($validated, $admin)->paginate($validated['per_page'] ?? 20);

            return ApiResponse::responseJson(true, 'Danh sách tòa nhà', 200, BuildingResource::collection($buildings), 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function store(RegisterRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $admin = $request->user('admin');

            if (! $admin || ! AdminScope::isSuperAdmin($admin)) {
                return ApiResponse::responseJson(false, 'Chỉ super admin mới được tạo tòa nhà', 403, null, 403);
            }

            $preflightError = $this->preflightBuildingSync($this->buildingSyncPreflightPayload($validated), null);

            if ($preflightError) {
                return $preflightError;
            }

            $response = DB::transaction(function () use ($validated, $admin, $request): JsonResponse {
                $building = Building::query()->create($this->payload($validated, $admin));

                $this->syncBuildingConfiguration($building, $validated, $admin);
                $this->syncBuildingImages($building, $validated, $request, $admin);
                $this->loadBuildingDetail($building);

                AdminActivityLogger::write($admin, 'Tạo tòa nhà', Building::class, $building->id, null, $building->toArray(), $request);

                return ApiResponse::responseJson(true, 'Tạo tòa nhà thành công', 201, new BuildingDetailResource($building), 201);
            });

            return $response;
        } catch (HttpResponseException $e) {
            return $e->getResponse();
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function show(Request $request, int $building): JsonResponse
    {
        try {
            $admin = $request->user('admin');

            if (! $admin || (! AdminScope::isSuperAdmin($admin) && ! AdminScope::isBuildingManager($admin))) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xem tòa nhà', 403, null, 403);
            }

            $buildingModel = $this->accessibleQuery($admin)
                ->select($this->columns())
                ->with($this->detailRelations())
                ->withCount($this->counts())
                ->find($building);

            if (! $buildingModel) {
                return ApiResponse::responseJson(false, 'Không tìm thấy tòa nhà', 404, null, 404);
            }

            return ApiResponse::responseJson(true, 'Chi tiết tòa nhà', 200, new BuildingDetailResource($buildingModel), 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function update(UpdateRequest $request, int $building): JsonResponse
    {
        try {
            $validated = $request->validated();
            $admin = $request->user('admin');

            if (! $admin || ! AdminScope::isSuperAdmin($admin)) {
                return ApiResponse::responseJson(false, 'Chỉ super admin mới được cập nhật tòa nhà', 403, null, 403);
            }

            if (! Building::query()->whereKey($building)->exists()) {
                return ApiResponse::responseJson(false, 'Không tìm thấy tòa nhà', 404, null, 404);
            }

            $preflightError = $this->preflightBuildingSync($this->buildingSyncPreflightPayload($validated), $building);

            if ($preflightError) {
                return $preflightError;
            }

            $response = DB::transaction(function () use ($validated, $building, $admin, $request): JsonResponse {
                $buildingModel = Building::query()->lockForUpdate()->find($building);

                if (! $buildingModel) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy tòa nhà', 404, null, 404);
                }

                $this->loadBuildingDetail($buildingModel);
                $oldData = $buildingModel->toArray();

                $buildingModel->fill($this->payload($validated, $admin, true))->save();

                $this->syncBuildingUpdateConfiguration($buildingModel, $validated, $request, $admin);
                $buildingModel->unsetRelations();
                $this->loadBuildingDetail($buildingModel);

                AdminActivityLogger::write($admin, 'Cập nhật tòa nhà', Building::class, $buildingModel->id, $oldData, $buildingModel->toArray(), $request);

                return ApiResponse::responseJson(true, 'Cập nhật tòa nhà thành công', 200, new BuildingDetailResource($buildingModel), 200);
            });

            return $response;
        } catch (HttpResponseException $e) {
            return $e->getResponse();
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function updateStatus(StatusRequest $request, int $building): JsonResponse
    {
        try {
            $validated = $request->validated();
            $admin = $request->user('admin');

            if (! $admin || ! AdminScope::isSuperAdmin($admin)) {
                return ApiResponse::responseJson(false, 'Chỉ super admin mới được đổi trạng thái tòa nhà', 403, null, 403);
            }

            $response = DB::transaction(function () use ($validated, $building, $admin, $request): JsonResponse {
                $buildingModel = Building::query()->lockForUpdate()->find($building);

                if (! $buildingModel) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy tòa nhà', 404, null, 404);
                }

                $oldData = $buildingModel->toArray();
                $buildingModel->forceFill(['status' => $validated['status']])->save();
                $this->loadBuildingDetail($buildingModel);

                AdminActivityLogger::write($admin, 'Cập nhật trạng thái tòa nhà', Building::class, $buildingModel->id, $oldData, $buildingModel->toArray(), $request);

                return ApiResponse::responseJson(true, 'Cập nhật trạng thái tòa nhà thành công', 200, new BuildingDetailResource($buildingModel), 200);
            });

            return $response;
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function destroy(Request $request, int $building): JsonResponse
    {
        try {
            $admin = $request->user('admin');

            if (! $admin || ! AdminScope::isSuperAdmin($admin)) {
                return ApiResponse::responseJson(false, 'Chỉ super admin mới được xóa tòa nhà', 403, null, 403);
            }

            $response = DB::transaction(function () use ($building, $admin, $request): JsonResponse {
                $buildingModel = Building::query()
                    ->withCount($this->deleteBlockingCounts())
                    ->lockForUpdate()
                    ->find($building);

                if (! $buildingModel) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy tòa nhà', 404, null, 404);
                }

                if ($this->hasDeleteBlockingData($buildingModel)) {
                    return ApiResponse::responseJson(false, 'Không thể xóa tòa nhà đang có dữ liệu phụ thuộc', 422, null, 422);
                }

                $oldData = $buildingModel->load('images')->toArray();
                $buildingModel->images->each(fn (BuildingImage $image): bool => ImageHelper::delete($image->image_path));
                $buildingModel->delete();

                AdminActivityLogger::write($admin, 'Xóa tòa nhà', Building::class, $buildingModel->id, $oldData, null, $request);

                return ApiResponse::responseJson(true, 'Xóa tòa nhà thành công', 200, null, 200);
            });

            return $response;
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function updateUtilityPrices(Request $request, int $building): JsonResponse
    {
        try {
            $admin = $request->user('admin');
            if (! $admin) {
                return ApiResponse::responseJson(false, 'Unauthorized', 401, null, 401);
            }

            if (! AdminScope::ensureBuildingAccess($admin, $building)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền quản lý tòa nhà này', 403, null, 403);
            }

            $buildingModel = Building::query()->find($building);
            if (! $buildingModel) {
                return ApiResponse::responseJson(false, 'Không tìm thấy tòa nhà', 404, null, 404);
            }

            $currentYear = now()->year;
            $currentMonth = now()->month;

            $validated = $request->validate([
                'electric_price' => 'required|numeric|min:0',
                'water_price' => 'required|numeric|min:0',
                'billing_month' => [
                    'required',
                    'integer',
                    'min:1',
                    'max:12',
                    function ($attribute, $value, $fail) use ($request, $currentYear, $currentMonth) {
                        $year = (int) $request->input('billing_year');
                        if ($year < $currentYear || ($year === $currentYear && (int) $value < $currentMonth)) {
                            $fail('Không thể thay đổi đơn giá cho tháng cũ.');
                        }
                    }
                ],
                'billing_year' => 'required|integer|min:' . $currentYear . '|max:2100',
            ]);

            $response = DB::transaction(function () use ($validated, $buildingModel, $admin, $request): JsonResponse {
                $startDate = \Illuminate\Support\Carbon::create($validated['billing_year'], $validated['billing_month'], 1)->toDateString();
                
                // Find services by slug
                $electricService = Service::whereIn('slug', ['electric', 'dien-sinh-hoat', 'dien'])->first();
                $waterService = Service::whereIn('slug', ['water', 'nuoc-sinh-hoat', 'nuoc'])->first();

                if (! $electricService || ! $waterService) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy cấu hình dịch vụ điện hoặc nước', 422, null, 422);
                }

                $servicesPayload = [
                    [
                        'service_id' => $electricService->id,
                        'price' => $validated['electric_price'],
                        'effective_from' => $startDate,
                        'status' => ServicePrice::STATUS_ACTIVE,
                    ],
                    [
                        'service_id' => $waterService->id,
                        'price' => $validated['water_price'],
                        'effective_from' => $startDate,
                        'status' => ServicePrice::STATUS_ACTIVE,
                    ],
                ];

                $syncError = $this->syncServicePrices($buildingModel, ['service_prices' => $servicesPayload], $admin);
                if ($syncError) {
                    return $syncError;
                }

                $activeElectricPrice = $this->activeServicePrice($buildingModel, $electricService->id);
                $activeWaterPrice = $this->activeServicePrice($buildingModel, $waterService->id);

                $updatedPrices = [
                    $electricService->slug => (float) ($activeElectricPrice ? $activeElectricPrice->price : $validated['electric_price']),
                    $waterService->slug => (float) ($activeWaterPrice ? $activeWaterPrice->price : $validated['water_price']),
                ];

                try {
                    $activeContractTenants = ContractTenant::query()
                        ->where('is_staying', true)
                        ->whereHas('contract', function ($q) use ($buildingModel) {
                            $q->where('status', Contract::STATUS_ACTIVE)
                                ->whereHas('room', function ($qr) use ($buildingModel) {
                                    $qr->where('building_id', $buildingModel->id);
                                });
                        })
                        ->with(['contract.room'])
                        ->get()
                        ->unique('tenant_id');

                    foreach ($activeContractTenants as $contractTenant) {
                        $tenantNotification = Notification::create([
                            'title' => 'Thay đổi đơn giá dịch vụ điện/nước',
                            'content' => "Tòa nhà {$buildingModel->name} áp dụng đơn giá dịch vụ mới từ tháng {$validated['billing_month']}/{$validated['billing_year']}: Điện " . number_format($validated['electric_price'], 0, ',', '.') . " đ/kWh, Nước " . number_format($validated['water_price'], 0, ',', '.') . " đ/m³.",
                            'notification_type' => Notification::NOTIFICATION_TYPE_SYSTEM,
                            'target_type' => Notification::TARGET_TYPE_TENANT,
                            'building_id' => $buildingModel->id,
                            'room_id' => $contractTenant->contract?->room_id,
                            'tenant_id' => $contractTenant->tenant_id,
                            'published_at' => now(),
                            'status' => Notification::STATUS_SENT,
                            'created_by' => $admin->id,
                        ]);

                        broadcast(new NotificationSent($tenantNotification));
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Error notifying tenants of utility price update: ' . $e->getMessage());
                }

                AdminActivityLogger::write($admin, 'Cập nhật giá điện nước', Building::class, $buildingModel->id, null, $updatedPrices, $request);

                return ApiResponse::responseJson(true, 'Cập nhật đơn giá dịch vụ thành công', 200, $updatedPrices, 200);
            });

            return $response;
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::responseJson(false, $e->validator->errors()->first(), 422, $e->validator->errors(), 422);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function utilityPriceHistory(Request $request, int $building): JsonResponse
    {
        try {
            $admin = $request->user('admin');
            if (! $admin) {
                return ApiResponse::responseJson(false, 'Unauthorized', 401, null, 401);
            }

            if (! AdminScope::ensureBuildingAccess($admin, $building)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền quản lý tòa nhà này', 403, null, 403);
            }

            $buildingModel = Building::query()->find($building);
            if (! $buildingModel) {
                return ApiResponse::responseJson(false, 'Không tìm thấy tòa nhà', 404, null, 404);
            }

            $electricService = Service::whereIn('slug', ['electric', 'dien-sinh-hoat', 'dien'])->first();
            $waterService = Service::whereIn('slug', ['water', 'nuoc-sinh-hoat', 'nuoc'])->first();

            if (! $electricService || ! $waterService) {
                return ApiResponse::responseJson(false, 'Không tìm thấy cấu hình dịch vụ điện hoặc nước', 422, null, 422);
            }

            $prices = ServicePrice::where('building_id', $building)
                ->whereIn('service_id', [$electricService->id, $waterService->id])
                ->with(['creator', 'service'])
                ->orderBy('effective_from', 'desc')
                ->orderBy('id', 'desc')
                ->get();

            $data = $prices->map(function ($price) {
                return [
                    'id' => $price->id,
                    'service_id' => $price->service_id,
                    'service_name' => $price->service?->name ?? 'Dịch vụ',
                    'price' => (float) $price->price,
                    'effective_from' => $price->effective_from->toDateString(),
                    'effective_to' => $price->effective_to ? $price->effective_to->toDateString() : null,
                    'status' => $price->status,
                    'status_label' => ServicePrice::STATUS_LABELS[$price->status] ?? 'Không xác định',
                    'created_by' => $price->created_by,
                    'creator_name' => $price->creator?->full_name ?? 'Hệ thống',
                    'created_at' => $price->created_at->toDateTimeString(),
                ];
            });

            return ApiResponse::responseJson(true, 'Lịch sử thay đổi đơn giá điện nước', 200, $data, 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    private function payload(array $validated, Admin $admin, bool $isUpdate = false): array
    {
        $payload = [];
        $fields = ['region_id', 'manager_admin_id', 'name', 'address', 'total_floors', 'gender_policy', 'description', 'status'];

        foreach ($fields as $field) {
            if (array_key_exists($field, $validated)) {
                $payload[$field] = $validated[$field];
            }
        }

        if (! $isUpdate) {
            $payload['created_by'] = $admin->id;
            $payload['gender_policy'] = $payload['gender_policy'] ?? Building::GENDER_POLICY_MIXED;
            $payload['status'] = $payload['status'] ?? Building::STATUS_ACTIVE;

        }

        return $payload;
    }

    private function syncBuildingUpdateConfiguration(Building $building, array $validated, Request $request, Admin $admin): void
    {
        $this->syncBuildingConfiguration($building, $validated, $admin);
        $this->syncBuildingImages($building, $validated, $request, $admin);
    }

    private function syncBuildingConfiguration(Building $building, array $validated, Admin $admin): void
    {
        foreach ([
            fn (): ?JsonResponse => $this->syncServicePrices($building, $validated, $admin),
            fn (): ?JsonResponse => $this->syncSettings($building, $validated, $admin),
        ] as $sync) {
            $errorResponse = $sync();

            if ($errorResponse) {
                throw new HttpResponseException($errorResponse);
            }
        }
    }

    private function buildingSyncPreflightPayload(array $validated): array
    {
        return [
            'service_price_ids' => $this->ids(collect($validated['service_prices'] ?? [])
                ->filter(fn ($servicePrice): bool => is_array($servicePrice))
                ->pluck('id')
                ->all()),
            'delete_service_price_ids' => $this->ids($validated['delete_service_price_ids'] ?? []),
            'setting_ids' => $this->ids($validated['setting_ids'] ?? []),
            'delete_setting_ids' => $this->ids($validated['delete_setting_ids'] ?? []),
            'settings' => collect($validated['settings'] ?? [])
                ->filter(fn ($setting): bool => is_array($setting))
                ->map(fn (array $setting): array => [
                    'id' => isset($setting['id']) ? (int) $setting['id'] : null,
                ])
                ->values()
                ->all(),
        ];
    }

    private function preflightBuildingSync(array $payload, ?int $buildingId): ?JsonResponse
    {
        $checks = [];

        if ($buildingId && ($payload['service_price_ids'] ?? []) !== []) {
            $checks['service_prices'] = static fn (): ?array => self::preflightServicePrices($payload, $buildingId);
        }

        if (($payload['setting_ids'] ?? []) !== [] || ($payload['settings'] ?? []) !== []) {
            $checks['settings'] = static fn (): ?array => self::preflightSettings($payload, $buildingId);
        }

        $error = collect($this->runBuildingPreflightChecks($checks))->first(fn ($result): bool => is_array($result));

        if (! $error) {
            return null;
        }

        return ApiResponse::responseJson(
            false,
            (string) $error['message'],
            (int) ($error['error_code'] ?? 422),
            $error['data'] ?? null,
            (int) ($error['http_code'] ?? 422)
        );
    }

    private function runBuildingPreflightChecks(array $checks): array
    {
        if ($checks === []) {
            return [];
        }

        if ($this->canDispatchOctaneTasks()) {
            try {
                $results = Octane::concurrently($checks, 3000);

                if (! in_array(false, $results, true)) {
                    return $results;
                }
            } catch (Throwable) {
            }
        }

        return collect($checks)
            ->mapWithKeys(fn (callable $check, string $key): array => [$key => $check()])
            ->all();
    }

    private function canDispatchOctaneTasks(): bool
    {
        return app()->bound(DispatchesTasks::class) || app()->bound(SwooleServer::class);
    }


    private static function preflightServicePrices(array $payload, ?int $buildingId): ?array
    {
        if (! $buildingId) {
            return null;
        }

        $deleteIds = $payload['delete_service_price_ids'] ?? [];
        $servicePriceIds = collect($payload['service_price_ids'] ?? [])
            ->reject(fn (int $servicePriceId): bool => in_array($servicePriceId, $deleteIds, true))
            ->values()
            ->all();

        if ($servicePriceIds === []) {
            return null;
        }

        $hasExpiredServicePrice = ServicePrice::query()
            ->where('building_id', $buildingId)
            ->whereIn('id', $servicePriceIds)
            ->where(fn (Builder $query): Builder => $query->where('status', ServicePrice::STATUS_EXPIRED)->orWhereNotNull('effective_to'))
            ->exists();

        return $hasExpiredServicePrice
            ? self::preflightError('Không thể cập nhật bảng giá dịch vụ đã hết hiệu lực')
            : null;
    }

    private static function preflightSettings(array $payload, ?int $buildingId): ?array
    {
        return null;
    }

    private static function preflightError(string $message, int $statusCode = 422): array
    {
        return [
            'message' => $message,
            'error_code' => $statusCode,
            'data' => null,
            'http_code' => $statusCode,
        ];
    }

    private function syncBuildingImages(Building $building, array $validated, Request $request, Admin $admin): void
    {
        $deleteIds = $this->ids($validated['delete_image_ids'] ?? []);

        if ($deleteIds !== []) {
            $building->images()->whereIn('id', $deleteIds)->get()->each(function (BuildingImage $image): void {
                ImageHelper::delete($image->image_path);
                $image->delete();
            });
        }

        collect($request->file('images', []))->each(function ($image, int $index) use ($building, $validated, $admin): void {
            $imageMeta = $validated['image_metadata'][$index] ?? [];

            $building->images()->create([
                'image_path' => ImageHelper::create($image, 'building'),
                'is_primary' => (bool) ($imageMeta['is_primary'] ?? false),
                'sort_order' => $imageMeta['sort_order'] ?? $index,
                'status' => $imageMeta['status'] ?? BuildingImage::STATUS_VISIBLE,
                'uploaded_by' => $admin->id,
            ]);
        });

        if (isset($validated['primary_image_id']) && ! in_array((int) $validated['primary_image_id'], $deleteIds, true) && $building->images()->whereKey($validated['primary_image_id'])->exists()) {
            $building->images()->update(['is_primary' => false]);
            $building->images()->whereKey($validated['primary_image_id'])->update(['is_primary' => true]);
        }

        $this->normalizePrimaryImage($building);
    }

    private function normalizePrimaryImage(Building $building): void
    {
        $primaryImageIds = $building->images()->where('is_primary', true)->orderBy('sort_order')->orderBy('id')->pluck('id');

        if ($primaryImageIds->count() > 1) {
            $building->images()->where('is_primary', true)->whereKeyNot($primaryImageIds->first())->update(['is_primary' => false]);
        }

        if (! $building->images()->where('is_primary', true)->exists()) {
            $building->images()->orderBy('sort_order')->orderBy('id')->first()?->update(['is_primary' => true]);
        }
    }


    private function syncServicePrices(Building $building, array $validated, ?Admin $admin = null): ?JsonResponse
    {
        $today = now()->toDateString();
        $deleteIds = $this->ids($validated['delete_service_price_ids'] ?? []);

        if ($deleteIds !== []) {
            $building->servicePrices()
                ->whereIn('id', $deleteIds)
                ->lockForUpdate()
                ->get()
                ->each(fn (ServicePrice $servicePrice): bool => $this->expireServicePrice($servicePrice, $today));
        }

        foreach ($validated['service_prices'] ?? [] as $servicePriceData) {
            $servicePriceId = isset($servicePriceData['id']) ? (int) $servicePriceData['id'] : null;

            if ($servicePriceId && in_array($servicePriceId, $deleteIds, true)) {
                continue;
            }

            $serviceId = (int) $servicePriceData['service_id'];
            $price = $this->normalizeDecimal($servicePriceData['price']);
            $status = (int) ($servicePriceData['status'] ?? ServicePrice::STATUS_ACTIVE);
            $effectiveFrom = $servicePriceData['effective_from'] ?? $today;
            
            $expireDate = \Illuminate\Support\Carbon::parse($effectiveFrom)->subDay()->toDateString();
            $effectiveTo = $status === ServicePrice::STATUS_EXPIRED ? ($servicePriceData['effective_to'] ?? $expireDate) : null;

            if ($servicePriceId) {
                $servicePrice = $building->servicePrices()->whereKey($servicePriceId)->lockForUpdate()->first();

                if (! $servicePrice) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy bảng giá dịch vụ trong tòa nhà này', 404, null, 404);
                }

                if ($this->isExpiredServicePrice($servicePrice)) {
                    return ApiResponse::responseJson(false, 'Không thể cập nhật bảng giá dịch vụ đã hết hiệu lực', 422, null, 422);
                }

                if ($status === ServicePrice::STATUS_EXPIRED) {
                    $this->expireServicePrice($servicePrice, $effectiveTo ?? $expireDate);

                    continue;
                }

                if (! $this->servicePriceChanged($servicePrice, $serviceId, $price)) {
                    $this->expireOtherActiveServicePrices($building, $serviceId, $expireDate, $servicePrice->id);
                    $servicePrice->forceFill([
                        'price' => $price,
                        'effective_to' => null,
                        'status' => ServicePrice::STATUS_ACTIVE,
                    ])->save();

                    continue;
                }

                $this->expireServicePrice($servicePrice, $expireDate);
                $this->expireOtherActiveServicePrices($building, $serviceId, $expireDate);
                $building->servicePrices()->create([
                    'service_id' => $serviceId,
                    'price' => $price,
                    'effective_from' => $effectiveFrom,
                    'effective_to' => null,
                    'status' => ServicePrice::STATUS_ACTIVE,
                    'created_by' => $admin?->id,
                ]);

                continue;
            }

            $activePrice = $this->activeServicePrice($building, $serviceId);

            if ($activePrice) {
                if ($status === ServicePrice::STATUS_EXPIRED) {
                    $this->expireServicePrice($activePrice, $effectiveTo ?? $expireDate);

                    continue;
                }

                if (! $this->servicePriceChanged($activePrice, $serviceId, $price)) {
                    continue;
                }

                $this->expireServicePrice($activePrice, $expireDate);
            }

            if ($status === ServicePrice::STATUS_ACTIVE) {
                $this->expireOtherActiveServicePrices($building, $serviceId, $expireDate);
            }

            $building->servicePrices()->create([
                'service_id' => $serviceId,
                'price' => $price,
                'effective_from' => $effectiveFrom,
                'effective_to' => $effectiveTo,
                'status' => $status,
                'created_by' => $admin?->id,
            ]);
        }

        return null;
    }

    private function syncSettings(Building $building, array $validated, Admin $admin): ?JsonResponse
    {
        $deleteIds = $this->ids($validated['delete_setting_ids'] ?? []);

        if ($deleteIds !== []) {
            $building->settings()->whereIn('id', $deleteIds)->delete();
        }

        foreach (Setting::query()->whereIn('id', $this->ids($validated['setting_ids'] ?? []))->lockForUpdate()->get() as $setting) {
            Setting::query()->create([
                'building_id' => $building->id,
                'setting_label' => $setting->setting_label,
                'setting_value' => $setting->setting_value,
                'description' => $setting->description,
                'is_public' => $setting->is_public,
                'created_by' => $setting->created_by ?: $admin->id,
            ]);
        }

        foreach ($validated['settings'] ?? [] as $settingData) {
            $settingId = isset($settingData['id']) ? (int) $settingData['id'] : null;

            if ($settingId && in_array($settingId, $deleteIds, true)) {
                continue;
            }

            $payload = [
                'setting_label' => trim((string) $settingData['setting_label']),
                'setting_value' => $settingData['setting_value'] ?? null,
                'description' => $settingData['description'] ?? null,
                'is_public' => $settingData['is_public'] ?? Setting::PUBLIC,
            ];

            if ($settingId) {
                $setting = $building->settings()->whereKey($settingId)->lockForUpdate()->first();

                if (! $setting) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy cài đặt trong tòa nhà này', 404, null, 404);
                }

                $setting->fill($payload)->save();

                continue;
            }

            $building->settings()->create($payload + ['created_by' => $admin->id]);
        }

        return null;
    }

    private function activeServicePrice(Building $building, int $serviceId): ?ServicePrice
    {
        return $building->servicePrices()
            ->where('service_id', $serviceId)
            ->where('status', ServicePrice::STATUS_ACTIVE)
            ->whereNull('effective_to')
            ->lockForUpdate()
            ->first();
    }

    private function servicePriceByEffectiveDate(Building $building, int $serviceId, string $effectiveFrom, ?int $ignoreId = null): ?ServicePrice
    {
        return $building->servicePrices()
            ->where('service_id', $serviceId)
            ->where('effective_from', $effectiveFrom)
            ->when($ignoreId !== null, fn (Builder $query): Builder => $query->whereKeyNot($ignoreId))
            ->lockForUpdate()
            ->first();
    }

    private function expireOtherActiveServicePrices(Building $building, int $serviceId, string $effectiveTo, ?int $ignoreId = null): void
    {
        $building->servicePrices()
            ->where('service_id', $serviceId)
            ->where('status', ServicePrice::STATUS_ACTIVE)
            ->whereNull('effective_to')
            ->when($ignoreId !== null, fn (Builder $query): Builder => $query->whereKeyNot($ignoreId))
            ->lockForUpdate()
            ->get()
            ->each(fn (ServicePrice $servicePrice): bool => $this->expireServicePrice($servicePrice, $effectiveTo));
    }

    private function expireServicePrice(ServicePrice $servicePrice, string $effectiveTo): bool
    {
        return $servicePrice->forceFill([
            'effective_to' => $effectiveTo,
            'status' => ServicePrice::STATUS_EXPIRED,
        ])->save();
    }

    private function isExpiredServicePrice(ServicePrice $servicePrice): bool
    {
        return (int) $servicePrice->status === ServicePrice::STATUS_EXPIRED || $servicePrice->effective_to !== null;
    }

    private function servicePriceEffectiveFrom(ServicePrice $servicePrice): ?string
    {
        return $servicePrice->effective_from?->toDateString();
    }

    private function servicePriceEffectiveDateConflictResponse(): JsonResponse
    {
        return ApiResponse::responseJson(false, 'Bảng giá dịch vụ đã tồn tại ngày hiệu lực này', 422, null, 422);
    }

    private function servicePriceChanged(ServicePrice $servicePrice, int $serviceId, mixed $price): bool
    {
        return (int) $servicePrice->service_id !== $serviceId || $this->normalizeDecimal($servicePrice->price) !== $this->normalizeDecimal($price);
    }

    private function normalizeDecimal(mixed $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '0.00';
        }

        [$integer, $decimal] = array_pad(explode('.', $value, 2), 2, '0');
        $integer = ltrim($integer, '0') ?: '0';
        $decimal = str_pad(substr($decimal, 0, 2), 2, '0');

        return $integer.'.'.$decimal;
    }



    private function columns(): array
    {
        return ['id', 'region_id', 'manager_admin_id', 'name', 'slug', 'address', 'total_floors', 'gender_policy', 'description', 'status', 'created_by', 'created_at', 'updated_at'];
    }

    private function listRelations(): array
    {
        return [
            'region:id,name',
            'manager:id,full_name',
            'primaryImage:id,building_id,image_path,is_primary,sort_order,status,uploaded_by,created_at,updated_at',
        ];
    }

    private function detailRelations(): array
    {
        return [
            'region:id,name,code,path,slug,is_active',
            'manager:id,username,full_name,email,phone,role,status',
            'creator:id,full_name',
            'primaryImage:id,building_id,image_path,is_primary,sort_order,status,uploaded_by,created_at,updated_at',
            'images:id,building_id,image_path,is_primary,sort_order,status,uploaded_by,created_at,updated_at',
            'images.uploader:id,full_name',
            'servicePrices' => fn ($query) => $query->select('id', 'service_id', 'building_id', 'price', 'effective_from', 'effective_to', 'status', 'created_at', 'updated_at')->with('service:id,name,slug,charge_method,unit_name,is_required,is_active,created_by,created_at,updated_at')->orderByDesc('effective_from')->orderByDesc('id'),
            'settings' => fn ($query) => $query->select('id', 'building_id', 'setting_label', 'setting_value', 'description', 'is_public', 'created_by', 'created_at', 'updated_at')->orderBy('setting_label'),
            'settings.creator:id,full_name',
            'rooms:id,building_id,room_number,status,max_occupants,current_occupants,base_price',
        ];
    }

    private function counts(): array
    {
        logger("counts: " . json_encode(['images', 'rooms', 'servicePrices', 'settings', 'notifications', 'expenses'])); return ['images', 'rooms', 'servicePrices', 'settings', 'notifications', 'expenses'];
    }

    private function deleteBlockingCounts(): array
    {
        return ['rooms', 'servicePrices', 'settings', 'notifications', 'expenses'];
    }

    private function hasDeleteBlockingData(Building $building): bool
    {
        return collect($this->deleteBlockingCounts())->contains(fn (string $relation): bool => (int) $building->{Str::snake($relation).'_count'} > 0);
    }

    private function queryBuildings(array $validated, Admin $admin): Builder
    {
        return $this->accessibleQuery($admin)
            ->select($this->columns())
            ->with($this->listRelations())
            ->withCount($this->counts())
            ->when(isset($validated['region_id']), fn (Builder $query): Builder => $query->where('region_id', $validated['region_id']))
            ->when(isset($validated['manager_admin_id']), fn (Builder $query): Builder => $query->where('manager_admin_id', $validated['manager_admin_id']))
            ->when(isset($validated['gender_policy']), fn (Builder $query): Builder => $query->where('gender_policy', $validated['gender_policy']))
            ->when(isset($validated['status']), fn (Builder $query): Builder => $query->where('status', $validated['status']))
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    private function searchBuildings(string $keyword, array $validated, Admin $admin): LengthAwarePaginator
    {
        $builder = Building::search($keyword);

        foreach (['region_id', 'manager_admin_id', 'gender_policy', 'status'] as $field) {
            if (isset($validated[$field])) {
                $builder->where($field, $validated[$field]);
            }
        }

        if (AdminScope::isBuildingManager($admin)) {
            $builder->where('manager_admin_id', $admin->id);
        }

        return $builder
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->query(fn ($query) => $this->applyAccessibleBuildingQuery($query, $admin)
                ->select($this->columns())
                ->with($this->listRelations())
                ->withCount($this->counts()))
            ->paginate($validated['per_page'] ?? 20);
    }

    private function accessibleQuery(Admin $admin): Builder
    {
        return $this->applyAccessibleBuildingQuery(Building::query(), $admin);
    }

    private function applyAccessibleBuildingQuery(Builder $query, Admin $admin): Builder
    {
        if (AdminScope::isSuperAdmin($admin)) {
            return $query;
        }

        if (AdminScope::isBuildingManager($admin)) {
            return $query->where('manager_admin_id', $admin->id);
        }

        return $query->whereRaw('1 = 0');
    }

    private function loadBuildingDetail(Building $building): Building
    {
        return $building->load($this->detailRelations())->loadCount($this->counts());
    }

    private function ids(array $values): array
    {
        return collect($values)
            ->filter(fn ($value): bool => $value !== null && $value !== '')
            ->map(fn ($value): int => (int) $value)
            ->unique()
            ->values()
            ->all();
    }
}
