<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AdminActivityLogger;
use App\Helpers\AdminScope;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssetTemplate\IndexRequest;
use App\Http\Requests\Admin\AssetTemplate\RegisterRequest;
use App\Http\Requests\Admin\AssetTemplate\StatusRequest;
use App\Http\Requests\Admin\AssetTemplate\UpdateRequest;
use App\Http\Resources\Admin\AssetTemplateDetailResource;
use App\Http\Resources\Admin\AssetTemplateResource;
use App\Models\Admin;
use App\Models\AssetTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssetTemplateController extends Controller
{
    public function index(IndexRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $admin = $request->user('admin');

            if (! $admin || ! $this->canUseAssetTemplates($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xem mẫu tài sản', 403, null, 403);
            }

            if (isset($validated['building_id']) && ! AdminScope::ensureBuildingAccess($admin, (int) $validated['building_id'])) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xem mẫu tài sản của tòa nhà này', 403, null, 403);
            }

            $assetTemplates = $this->queryAssetTemplates($validated, $admin)->paginate($validated['per_page'] ?? 20);

            return ApiResponse::responseJson(true, 'Danh sách mẫu tài sản', 200, AssetTemplateResource::collection($assetTemplates), 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function store(RegisterRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $admin = $request->user('admin');

            if (! $admin || ! $this->canUseAssetTemplates($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền tạo mẫu tài sản', 403, null, 403);
            }

            if (! $this->canWriteAssetTemplateForBuilding($admin, $validated['building_id'] ?? null)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền tạo mẫu tài sản cho tòa nhà này', 403, null, 403);
            }

            $response = DB::transaction(function () use ($validated, $admin, $request): JsonResponse {
                $assetTemplate = AssetTemplate::query()->create($this->payload($validated, $admin));

                AdminActivityLogger::write($admin, 'create_asset_template', AssetTemplate::class, $assetTemplate->id, null, $assetTemplate->toArray(), $request);

                $assetTemplate->load($this->storeRelations())->loadCount($this->counts());

                return ApiResponse::responseJson(true, 'Tạo mẫu tài sản thành công', 201, new AssetTemplateDetailResource($assetTemplate), 201);
            });

            return $response;
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function show(Request $request, int $assetTemplate): JsonResponse
    {
        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canUseAssetTemplates($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xem mẫu tài sản', 403, null, 403);
            }

            $assetTemplateModel = $this->accessibleQuery($admin)
                ->select($this->columns())
                ->with($this->detailRelations())
                ->withCount($this->counts())
                ->find($assetTemplate);

            if (! $assetTemplateModel) {
                return ApiResponse::responseJson(false, 'Không tìm thấy mẫu tài sản', 404, null, 404);
            }

            return ApiResponse::responseJson(true, 'Chi tiết mẫu tài sản', 200, new AssetTemplateDetailResource($assetTemplateModel), 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function update(UpdateRequest $request, int $assetTemplate): JsonResponse
    {
        try {
            $validated = $request->validated();
            $admin = $request->user('admin');

            if (! $admin || ! $this->canUseAssetTemplates($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền cập nhật mẫu tài sản', 403, null, 403);
            }

            if (array_key_exists('building_id', $validated) && ! $this->canWriteAssetTemplateForBuilding($admin, $validated['building_id'])) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền chuyển mẫu tài sản sang tòa nhà này', 403, null, 403);
            }

            $response = DB::transaction(function () use ($validated, $assetTemplate, $admin, $request): JsonResponse {
                $assetTemplateModel = $this->accessibleQuery($admin)->lockForUpdate()->find($assetTemplate);

                if (! $assetTemplateModel) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy mẫu tài sản', 404, null, 404);
                }

                if (! $this->canWriteAssetTemplateForBuilding($admin, $assetTemplateModel->building_id)) {
                    return ApiResponse::responseJson(false, 'Bạn không có quyền cập nhật mẫu tài sản này', 403, null, 403);
                }

                $oldData = $assetTemplateModel->toArray();
                $assetTemplateModel->fill($this->payload($validated, $admin, true))->save();

                AdminActivityLogger::write($admin, 'update_asset_template', AssetTemplate::class, $assetTemplateModel->id, $oldData, $assetTemplateModel->fresh()->toArray(), $request);

                $assetTemplateModel->load($this->storeRelations())->loadCount($this->counts());

                return ApiResponse::responseJson(true, 'Cập nhật mẫu tài sản thành công', 200, new AssetTemplateDetailResource($assetTemplateModel), 200);
            });

            return $response;
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function updateStatus(StatusRequest $request, int $assetTemplate): JsonResponse
    {
        try {
            $validated = $request->validated();
            $admin = $request->user('admin');

            if (! $admin || ! $this->canUseAssetTemplates($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền đổi trạng thái mẫu tài sản', 403, null, 403);
            }

            $response = DB::transaction(function () use ($validated, $assetTemplate, $admin, $request): JsonResponse {
                $assetTemplateModel = $this->accessibleQuery($admin)->lockForUpdate()->find($assetTemplate);

                if (! $assetTemplateModel) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy mẫu tài sản', 404, null, 404);
                }

                if (! $this->canWriteAssetTemplateForBuilding($admin, $assetTemplateModel->building_id)) {
                    return ApiResponse::responseJson(false, 'Bạn không có quyền đổi trạng thái mẫu tài sản này', 403, null, 403);
                }

                $oldData = $assetTemplateModel->toArray();
                $assetTemplateModel->forceFill(['status' => $validated['status']])->save();

                AdminActivityLogger::write($admin, 'update_asset_template_status', AssetTemplate::class, $assetTemplateModel->id, $oldData, $assetTemplateModel->fresh()->toArray(), $request);

                $assetTemplateModel->load($this->storeRelations())->loadCount($this->counts());

                return ApiResponse::responseJson(true, 'Cập nhật trạng thái mẫu tài sản thành công', 200, new AssetTemplateDetailResource($assetTemplateModel), 200);
            });

            return $response;
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function destroy(Request $request, int $assetTemplate): JsonResponse
    {
        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canUseAssetTemplates($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xóa mẫu tài sản', 403, null, 403);
            }

            $response = DB::transaction(function () use ($assetTemplate, $admin, $request): JsonResponse {
                $assetTemplateModel = $this->accessibleQuery($admin)->withCount('roomAssets')->lockForUpdate()->find($assetTemplate);

                if (! $assetTemplateModel) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy mẫu tài sản', 404, null, 404);
                }

                if (! $this->canWriteAssetTemplateForBuilding($admin, $assetTemplateModel->building_id)) {
                    return ApiResponse::responseJson(false, 'Bạn không có quyền xóa mẫu tài sản này', 403, null, 403);
                }

                if ((int) $assetTemplateModel->room_assets_count > 0) {
                    return ApiResponse::responseJson(false, 'Không thể xóa mẫu tài sản đang được gán cho phòng', 422, null, 422);
                }

                $oldData = $assetTemplateModel->toArray();
                $assetTemplateModel->delete();

                AdminActivityLogger::write($admin, 'delete_asset_template', AssetTemplate::class, $assetTemplateModel->id, $oldData, null, $request);

                return ApiResponse::responseJson(true, 'Xóa mẫu tài sản thành công', 200, null, 200);
            });

            return $response;
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    private function payload(array $validated, Admin $admin, bool $isUpdate = false): array
    {
        $payload = [];
        $fields = ['name', 'building_id', 'default_unit_name', 'description', 'status', 'created_by'];

        foreach ($fields as $field) {
            if (array_key_exists($field, $validated)) {
                $payload[$field] = $validated[$field];
            }
        }

        if (! $isUpdate) {
            $payload['created_by'] = $admin->id;
            $payload['default_unit_name'] = $payload['default_unit_name'] ?? AssetTemplate::UNIT_PIECE;
            $payload['status'] = $payload['status'] ?? AssetTemplate::STATUS_ACTIVE;
        }

        return $payload;
    }

    private function columns(): array
    {
        return ['id', 'name', 'slug', 'building_id', 'default_unit_name', 'description', 'status', 'created_by', 'created_at', 'updated_at'];
    }

    private function listRelations(): array
    {
        return ['building:id,name,slug,address,status', 'creator:id,full_name'];
    }

    private function storeRelations(): array
    {
        return ['building:id,name,slug,address,status', 'creator:id,full_name'];
    }

    private function detailRelations(): array
    {
        return ['building:id,region_id,manager_admin_id,name,slug,address,total_floors,gender_policy,description,status,created_by,created_at,updated_at', 'creator:id,full_name'];
    }

    private function counts(): array
    {
        return ['roomAssets'];
    }

    private function queryAssetTemplates(array $validated, Admin $admin): Builder
    {
        $keyword = trim($validated['keyword'] ?? '');

        return $this->accessibleQuery($admin)
            ->select($this->columns())
            ->with($this->listRelations())
            ->withCount($this->counts())
            ->when($keyword !== '', fn (Builder $query): Builder => $query->where(function (Builder $keywordQuery) use ($keyword): void {
                $keywordQuery->where('name', 'like', "%{$keyword}%")
                    ->orWhere('description', 'like', "%{$keyword}%");
            }))
            ->when(isset($validated['building_id']), fn (Builder $query): Builder => $query->where('building_id', $validated['building_id']))
            ->when(isset($validated['default_unit_name']), fn (Builder $query): Builder => $query->where('default_unit_name', $validated['default_unit_name']))
            ->when(isset($validated['status']), fn (Builder $query): Builder => $query->where('status', $validated['status']))
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    private function accessibleQuery(Admin $admin): Builder
    {
        $query = AssetTemplate::query();

        if (AdminScope::isSuperAdmin($admin)) {
            return $query;
        }

        if (AdminScope::isBuildingManager($admin)) {
            return $query->where(function (Builder $scopeQuery) use ($admin): void {
                $scopeQuery->whereNull('asset_templates.building_id')
                    ->orWhereHas('building', fn (Builder $buildingQuery): Builder => $buildingQuery->where('manager_admin_id', $admin->id));
            });
        }

        return $query->whereRaw('1 = 0');
    }

    private function canUseAssetTemplates(Admin $admin): bool
    {
        return AdminScope::isSuperAdmin($admin) || AdminScope::isBuildingManager($admin);
    }

    private function canWriteAssetTemplateForBuilding(Admin $admin, mixed $buildingId): bool
    {
        if (AdminScope::isSuperAdmin($admin)) {
            return true;
        }

        if (! AdminScope::isBuildingManager($admin) || empty($buildingId)) {
            return false;
        }

        return AdminScope::ensureBuildingAccess($admin, (int) $buildingId);
    }
}
