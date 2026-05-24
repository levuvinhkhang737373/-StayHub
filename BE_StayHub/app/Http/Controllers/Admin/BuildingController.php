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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

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

                $primaryImageIds = $building->images()->where('is_primary', true)->orderBy('sort_order')->orderBy('id')->pluck('id');

                if ($primaryImageIds->count() > 1) {
                    $building->images()->where('is_primary', true)->whereKeyNot($primaryImageIds->first())->update(['is_primary' => false]);
                }

                if (! $building->images()->where('is_primary', true)->exists()) {
                    $building->images()->orderBy('sort_order')->orderBy('id')->first()?->update(['is_primary' => true]);
                }

                AdminActivityLogger::write($admin, 'create_building', Building::class, $building->id, null, $building->toArray(), $request);

                $building->load($this->storeRelations())->loadCount($this->counts());

                return ApiResponse::responseJson(true, 'Tạo tòa nhà thành công', 201, new BuildingDetailResource($building), 201);
            });

            return $response;
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

                $oldData = $buildingModel->load('images')->toArray();

                $buildingModel->images()->whereIn('id', $validated['delete_image_ids'] ?? [])->get()->each(function (BuildingImage $image): void {
                    ImageHelper::delete($image->image_path);
                    $image->delete();
                });

                $buildingModel->fill($this->payload($validated, $admin, true))->save();

                collect($request->file('images', []))->each(function ($image, int $index) use ($buildingModel, $validated, $admin): void {
                    $imageMeta = $validated['image_metadata'][$index] ?? [];

                    $buildingModel->images()->create([
                        'image_path' => ImageHelper::create($image, 'building'),
                        'is_primary' => (bool) ($imageMeta['is_primary'] ?? false),
                        'sort_order' => $imageMeta['sort_order'] ?? $index,
                        'status' => $imageMeta['status'] ?? BuildingImage::STATUS_VISIBLE,
                        'uploaded_by' => $admin->id,
                    ]);
                });

                if (isset($validated['primary_image_id']) && $buildingModel->images()->whereKey($validated['primary_image_id'])->exists()) {
                    $buildingModel->images()->update(['is_primary' => false]);
                    $buildingModel->images()->whereKey($validated['primary_image_id'])->update(['is_primary' => true]);
                }

                $primaryImageIds = $buildingModel->images()->where('is_primary', true)->orderBy('sort_order')->orderBy('id')->pluck('id');

                if ($primaryImageIds->count() > 1) {
                    $buildingModel->images()->where('is_primary', true)->whereKeyNot($primaryImageIds->first())->update(['is_primary' => false]);
                }

                if (! $buildingModel->images()->where('is_primary', true)->exists()) {
                    $buildingModel->images()->orderBy('sort_order')->orderBy('id')->first()?->update(['is_primary' => true]);
                }

                AdminActivityLogger::write($admin, 'update_building', Building::class, $buildingModel->id, $oldData, $buildingModel->fresh(['images'])->toArray(), $request);

                $buildingModel->load($this->storeRelations())->loadCount($this->counts());

                return ApiResponse::responseJson(true, 'Cập nhật tòa nhà thành công', 200, new BuildingDetailResource($buildingModel), 200);
            });

            return $response;
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

                AdminActivityLogger::write($admin, 'update_building_status', Building::class, $buildingModel->id, $oldData, $buildingModel->fresh()->toArray(), $request);

                $buildingModel->load($this->storeRelations())->loadCount($this->counts());

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
        $fields = ['region_id', 'manager_admin_id', 'name', 'address', 'total_floors', 'gender_policy', 'description', 'status', 'created_by'];

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

    private function storeRelations(): array
    {
        return [
            'region:id,name,code,path,slug,is_active',
            'manager:id,username,full_name,email,phone,role,status',
            'creator:id,full_name',
            'primaryImage:id,building_id,image_path,is_primary,sort_order,status,uploaded_by,created_at,updated_at',
            'images:id,building_id,image_path,is_primary,sort_order,status,uploaded_by,created_at,updated_at',
            'images.uploader:id,full_name',
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
        ];
    }

    private function counts(): array
    {
        return ['images', 'rooms', 'roomTypes', 'assetTemplates', 'servicePrices', 'notifications', 'expenses'];
    }

    private function deleteBlockingCounts(): array
    {
        return ['rooms', 'roomTypes', 'assetTemplates', 'servicePrices', 'notifications', 'expenses', 'settings'];
    }

    private function hasDeleteBlockingData(Building $building): bool
    {
        return collect($this->deleteBlockingCounts())->contains(fn (string $relation): bool => (int) $building->{$relation.'_count'} > 0);
    }

    private function queryBuildings(array $validated, Admin $admin): Builder
    {
        return $this->accessibleQuery($admin)
            ->select($this->columns())
            ->with($this->listRelations())
            ->withCount($this->counts())
            ->when(isset($validated['region_id']), fn ($query) => $query->where('region_id', $validated['region_id']))
            ->when(isset($validated['manager_admin_id']), fn ($query) => $query->where('manager_admin_id', $validated['manager_admin_id']))
            ->when(isset($validated['gender_policy']), fn ($query) => $query->where('gender_policy', $validated['gender_policy']))
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
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
            ->query(fn ($query) => $this->applyAccessibleBuildingQuery($query, $admin)
                ->select($this->columns())
                ->with($this->listRelations())
                ->withCount($this->counts())
                ->orderByDesc('created_at')
                ->orderByDesc('id'))
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
}

