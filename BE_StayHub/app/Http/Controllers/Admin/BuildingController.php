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
use App\Models\AssetTemplate;
use App\Models\Building;
use App\Models\BuildingImage;
use App\Models\RoomType;
use App\Models\ServicePrice;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

            $response = DB::transaction(function () use ($validated, $admin, $request): JsonResponse {
                $building = Building::query()->create($this->payload($validated, $admin));

                foreach ([
                    fn (): ?JsonResponse => $this->syncRoomTypes($building, $validated, $admin),
                    fn (): ?JsonResponse => $this->syncAssetTemplates($building, $validated, $admin),
                    fn (): ?JsonResponse => $this->syncServicePrices($building, $validated),
                    fn (): ?JsonResponse => $this->syncSettings($building, $validated, $admin),
                ] as $sync) {
                    $errorResponse = $sync();

                    if ($errorResponse) {
                        throw new HttpResponseException($errorResponse);
                    }
                }

                $this->syncBuildingImages($building, $validated, $request, $admin);
                $this->loadBuildingDetail($building);

                AdminActivityLogger::write($admin, 'create_building', Building::class, $building->id, null, $building->toArray(), $request);

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

                AdminActivityLogger::write($admin, 'update_building', Building::class, $buildingModel->id, $oldData, $buildingModel->toArray(), $request);

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

                AdminActivityLogger::write($admin, 'update_building_status', Building::class, $buildingModel->id, $oldData, $buildingModel->toArray(), $request);

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

                AdminActivityLogger::write($admin, 'delete_building', Building::class, $buildingModel->id, $oldData, null, $request);

                return ApiResponse::responseJson(true, 'Xóa tòa nhà thành công', 200, null, 200);
            });

            return $response;
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
        foreach ([
            fn (): ?JsonResponse => $this->syncRoomTypes($building, $validated, $admin),
            fn (): ?JsonResponse => $this->syncAssetTemplates($building, $validated, $admin),
            fn (): ?JsonResponse => $this->syncServicePrices($building, $validated),
            fn (): ?JsonResponse => $this->syncSettings($building, $validated, $admin),
        ] as $sync) {
            $errorResponse = $sync();

            if ($errorResponse) {
                throw new HttpResponseException($errorResponse);
            }
        }

        $this->syncBuildingImages($building, $validated, $request, $admin);
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

    private function syncRoomTypes(Building $building, array $validated, Admin $admin): ?JsonResponse
    {
        $deleteIds = $this->ids($validated['delete_room_type_ids'] ?? []);

        if ($deleteIds !== []) {
            $blockedRoomType = $building->roomTypes()->whereIn('id', $deleteIds)->withCount('rooms')->get()->first(fn (RoomType $roomType): bool => (int) $roomType->rooms_count > 0);

            if ($blockedRoomType) {
                return ApiResponse::responseJson(false, 'Không thể xóa loại phòng đang được gán cho phòng', 422, null, 422);
            }

            $building->roomTypes()->whereIn('id', $deleteIds)->delete();
        }

        foreach (RoomType::query()->whereIn('id', $this->ids($validated['room_type_ids'] ?? []))->whereNull('building_id')->lockForUpdate()->get() as $roomType) {
            if ($this->roomTypeNameExists($building, $roomType->name)) {
                return ApiResponse::responseJson(false, 'Tên loại phòng đã tồn tại trong tòa nhà này', 422, null, 422);
            }

            $roomType->forceFill([
                'building_id' => $building->id,
                'created_by' => $roomType->created_by ?: $admin->id,
            ])->save();
        }

        foreach ($validated['room_types'] ?? [] as $roomTypeData) {
            $roomTypeId = isset($roomTypeData['id']) ? (int) $roomTypeData['id'] : null;

            if ($roomTypeId && in_array($roomTypeId, $deleteIds, true)) {
                continue;
            }

            $name = trim((string) $roomTypeData['name']);

            if ($this->roomTypeNameExists($building, $name, $roomTypeId)) {
                return ApiResponse::responseJson(false, 'Tên loại phòng đã tồn tại trong tòa nhà này', 422, null, 422);
            }

            $payload = [
                'name' => $name,
                'description' => $roomTypeData['description'] ?? null,
                'status' => $roomTypeData['status'] ?? RoomType::STATUS_ACTIVE,
            ];

            if ($roomTypeId) {
                $roomType = $building->roomTypes()->whereKey($roomTypeId)->lockForUpdate()->first();

                if (! $roomType) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy loại phòng trong tòa nhà này', 404, null, 404);
                }

                $roomType->fill($payload)->save();

                continue;
            }

            $building->roomTypes()->create($payload + ['created_by' => $admin->id]);
        }

        return null;
    }

    private function syncAssetTemplates(Building $building, array $validated, Admin $admin): ?JsonResponse
    {
        $deleteIds = $this->ids($validated['delete_asset_template_ids'] ?? []);

        if ($deleteIds !== []) {
            $blockedAssetTemplate = $building->assetTemplates()->whereIn('id', $deleteIds)->withCount('roomAssets')->get()->first(fn (AssetTemplate $assetTemplate): bool => (int) $assetTemplate->room_assets_count > 0);

            if ($blockedAssetTemplate) {
                return ApiResponse::responseJson(false, 'Không thể xóa mẫu tài sản đang được gán cho phòng', 422, null, 422);
            }

            $building->assetTemplates()->whereIn('id', $deleteIds)->delete();
        }

        foreach (AssetTemplate::query()->whereIn('id', $this->ids($validated['asset_template_ids'] ?? []))->whereNull('building_id')->lockForUpdate()->get() as $assetTemplate) {
            if ($building->assetTemplates()->where('name', $assetTemplate->name)->exists()) {
                return ApiResponse::responseJson(false, 'Tên mẫu tài sản đã tồn tại trong tòa nhà này', 422, null, 422);
            }

            $assetTemplate->forceFill([
                'building_id' => $building->id,
                'created_by' => $assetTemplate->created_by ?: $admin->id,
            ])->save();
        }

        foreach ($validated['asset_templates'] ?? [] as $assetTemplateData) {
            $assetTemplateId = isset($assetTemplateData['id']) ? (int) $assetTemplateData['id'] : null;

            if ($assetTemplateId && in_array($assetTemplateId, $deleteIds, true)) {
                continue;
            }

            $payload = [
                'name' => trim((string) $assetTemplateData['name']),
                'default_unit_name' => $assetTemplateData['default_unit_name'] ?? AssetTemplate::UNIT_PIECE,
                'description' => $assetTemplateData['description'] ?? null,
                'status' => $assetTemplateData['status'] ?? AssetTemplate::STATUS_ACTIVE,
            ];

            if ($assetTemplateId) {
                $assetTemplate = $building->assetTemplates()->whereKey($assetTemplateId)->lockForUpdate()->first();

                if (! $assetTemplate) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy mẫu tài sản trong tòa nhà này', 404, null, 404);
                }

                $assetTemplate->fill($payload)->save();

                continue;
            }

            $building->assetTemplates()->create($payload + ['created_by' => $admin->id]);
        }

        return null;
    }

    private function syncServicePrices(Building $building, array $validated): ?JsonResponse
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
            $effectiveTo = $status === ServicePrice::STATUS_EXPIRED ? ($servicePriceData['effective_to'] ?? $today) : null;

            if ($servicePriceId) {
                $servicePrice = $building->servicePrices()->whereKey($servicePriceId)->lockForUpdate()->first();

                if (! $servicePrice) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy bảng giá dịch vụ trong tòa nhà này', 404, null, 404);
                }

                if ($this->isExpiredServicePrice($servicePrice)) {
                    return ApiResponse::responseJson(false, 'Không thể cập nhật bảng giá dịch vụ đã hết hiệu lực', 422, null, 422);
                }

                if ($status === ServicePrice::STATUS_EXPIRED) {
                    $this->expireServicePrice($servicePrice, $effectiveTo ?? $today);

                    continue;
                }

                if (! $this->servicePriceChanged($servicePrice, $serviceId, $price)) {
                    $this->expireOtherActiveServicePrices($building, $serviceId, $today, $servicePrice->id);
                    $servicePrice->forceFill([
                        'price' => $price,
                        'effective_to' => null,
                        'status' => ServicePrice::STATUS_ACTIVE,
                    ])->save();

                    continue;
                }

                if ($this->servicePriceEffectiveFrom($servicePrice) === $today) {
                    $sameDatePrice = $this->servicePriceByEffectiveDate($building, $serviceId, $today, $servicePrice->id);

                    if ($sameDatePrice) {
                        if ($this->isExpiredServicePrice($sameDatePrice)) {
                            return $this->servicePriceEffectiveDateConflictResponse();
                        }

                        $this->expireServicePrice($servicePrice, $today);
                        $this->expireOtherActiveServicePrices($building, $serviceId, $today, $sameDatePrice->id);
                        $sameDatePrice->forceFill([
                            'price' => $price,
                            'effective_to' => null,
                            'status' => ServicePrice::STATUS_ACTIVE,
                        ])->save();

                        continue;
                    }

                    $this->expireOtherActiveServicePrices($building, $serviceId, $today, $servicePrice->id);
                    $servicePrice->forceFill([
                        'service_id' => $serviceId,
                        'price' => $price,
                        'effective_to' => null,
                        'status' => ServicePrice::STATUS_ACTIVE,
                    ])->save();

                    continue;
                }

                $sameDatePrice = $this->servicePriceByEffectiveDate($building, $serviceId, $today, $servicePrice->id);

                if ($sameDatePrice) {
                    if ($this->isExpiredServicePrice($sameDatePrice)) {
                        return $this->servicePriceEffectiveDateConflictResponse();
                    }

                    $this->expireServicePrice($servicePrice, $today);
                    $this->expireOtherActiveServicePrices($building, $serviceId, $today, $sameDatePrice->id);
                    $sameDatePrice->forceFill([
                        'price' => $price,
                        'effective_to' => null,
                        'status' => ServicePrice::STATUS_ACTIVE,
                    ])->save();

                    continue;
                }

                $this->expireServicePrice($servicePrice, $today);
                $this->expireOtherActiveServicePrices($building, $serviceId, $today);
                $building->servicePrices()->create([
                    'service_id' => $serviceId,
                    'price' => $price,
                    'effective_from' => $today,
                    'effective_to' => null,
                    'status' => ServicePrice::STATUS_ACTIVE,
                ]);

                continue;
            }

            $activePrice = $this->activeServicePrice($building, $serviceId);

            if ($activePrice) {
                if ($status === ServicePrice::STATUS_EXPIRED) {
                    $this->expireServicePrice($activePrice, $effectiveTo ?? $today);

                    continue;
                }

                if (! $this->servicePriceChanged($activePrice, $serviceId, $price)) {
                    continue;
                }

                if ($this->servicePriceEffectiveFrom($activePrice) === $effectiveFrom) {
                    $activePrice->forceFill([
                        'price' => $price,
                        'effective_to' => null,
                        'status' => ServicePrice::STATUS_ACTIVE,
                    ])->save();

                    continue;
                }
            }

            $sameDatePrice = $this->servicePriceByEffectiveDate($building, $serviceId, $effectiveFrom, $activePrice?->id);

            if ($sameDatePrice) {
                if ($this->isExpiredServicePrice($sameDatePrice)) {
                    return $this->servicePriceEffectiveDateConflictResponse();
                }

                $this->expireOtherActiveServicePrices($building, $serviceId, $today, $sameDatePrice->id);
                $sameDatePrice->forceFill([
                    'price' => $price,
                    'effective_to' => $effectiveTo,
                    'status' => $status,
                ])->save();

                continue;
            }

            if ($activePrice) {
                $this->expireServicePrice($activePrice, $today);
            }

            if ($status === ServicePrice::STATUS_ACTIVE) {
                $this->expireOtherActiveServicePrices($building, $serviceId, $today);
            }

            $building->servicePrices()->create([
                'service_id' => $serviceId,
                'price' => $price,
                'effective_from' => $effectiveFrom,
                'effective_to' => $effectiveTo,
                'status' => $status,
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

        foreach (Setting::query()->whereIn('id', $this->ids($validated['setting_ids'] ?? []))->whereNull('building_id')->lockForUpdate()->get() as $setting) {
            if ($this->settingNameExists($building, $setting->setting_name)) {
                return ApiResponse::responseJson(false, 'Khóa cài đặt đã tồn tại trong tòa nhà này', 422, null, 422);
            }

            $setting->forceFill([
                'building_id' => $building->id,
                'created_by' => $setting->created_by ?: $admin->id,
            ])->save();
        }

        foreach ($validated['settings'] ?? [] as $settingData) {
            $settingId = isset($settingData['id']) ? (int) $settingData['id'] : null;

            if ($settingId && in_array($settingId, $deleteIds, true)) {
                continue;
            }

            $settingName = trim((string) $settingData['setting_name']);

            if ($this->settingNameExists($building, $settingName, $settingId)) {
                return ApiResponse::responseJson(false, 'Khóa cài đặt đã tồn tại trong tòa nhà này', 422, null, 422);
            }

            $payload = [
                'setting_label' => trim((string) $settingData['setting_label']),
                'setting_name' => $settingName,
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

    private function roomTypeNameExists(Building $building, string $name, ?int $ignoreId = null): bool
    {
        return $building->roomTypes()
            ->where('name', $name)
            ->when($ignoreId !== null, fn (Builder $query): Builder => $query->whereKeyNot($ignoreId))
            ->exists();
    }

    private function settingNameExists(Building $building, string $settingName, ?int $ignoreId = null): bool
    {
        return $building->settings()
            ->where('setting_name', $settingName)
            ->when($ignoreId !== null, fn (Builder $query): Builder => $query->whereKeyNot($ignoreId))
            ->exists();
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
            'roomTypes' => fn ($query) => $query->select('id', 'name', 'slug', 'building_id', 'description', 'status', 'created_by', 'created_at', 'updated_at')->withCount('rooms')->orderBy('name'),
            'assetTemplates' => fn ($query) => $query->select('id', 'name', 'slug', 'building_id', 'default_unit_name', 'description', 'status', 'created_by', 'created_at', 'updated_at')->withCount('roomAssets')->orderBy('name'),
            'servicePrices' => fn ($query) => $query->select('id', 'service_id', 'building_id', 'price', 'effective_from', 'effective_to', 'status', 'created_at', 'updated_at')->with('service:id,service_code,name,slug,service_type,charge_method,unit_name,is_required,is_active,created_by,created_at,updated_at')->orderByDesc('effective_from')->orderByDesc('id'),
            'settings' => fn ($query) => $query->select('id', 'building_id', 'setting_label', 'setting_name', 'setting_value', 'description', 'is_public', 'created_by', 'created_at', 'updated_at')->orderBy('setting_name'),
            'settings.creator:id,full_name',
        ];
    }

    private function counts(): array
    {
        return ['images', 'rooms', 'roomTypes', 'assetTemplates', 'servicePrices', 'settings', 'notifications', 'expenses'];
    }

    private function deleteBlockingCounts(): array
    {
        return ['rooms', 'roomTypes', 'assetTemplates', 'servicePrices', 'settings', 'notifications', 'expenses'];
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
