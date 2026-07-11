<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AdminActivityLogger;
use App\Helpers\AdminScope;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Region\IndexRequest;
use App\Http\Requests\Admin\Region\RegisterRequest;
use App\Http\Requests\Admin\Region\StatusRequest;
use App\Http\Requests\Admin\Region\UpdateRequest;
use App\Http\Resources\Admin\RegionDetailResource;
use App\Http\Resources\Admin\RegionResource;
use App\Models\Region;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class RegionController extends Controller
{
    // Danh sách các khu vực/chi nhánh quản lý
    public function index(IndexRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $keyword = trim($validated['keyword'] ?? '');
            $regions = $keyword !== ''
                ? $this->searchRegions($keyword, $validated)
                : $this->queryRegions($validated)->paginate($validated['per_page'] ?? 20);

            return ApiResponse::responseJson(true, 'Danh sách khu vực', 200, RegionResource::collection($regions), 200);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    // Tạo mới khu vực
    public function store(RegisterRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $admin = $request->user('admin');

            if (! $admin || ! AdminScope::isSuperAdmin($admin)) {
                return ApiResponse::responseJson(false, 'Chỉ super admin mới được tạo khu vực', 403, null, 403);
            }

            $response = DB::transaction(function () use ($validated, $admin, $request): JsonResponse {
                $region = Region::query()->create($this->payload($validated, $admin?->id));

                if ($admin) {
                    AdminActivityLogger::write($admin, 'Tạo khu vực', Region::class, $region->id, null, $region->toArray(), $request);
                }

                $region->load($this->storeRelations())->loadCount($this->counts());

                return ApiResponse::responseJson(true, 'Tạo khu vực thành công', 201, new RegionDetailResource($region), 201);
            });

            return $response;
        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e);

            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    // Xem chi tiết khu vực
    public function show(int $region): JsonResponse
    {
        try {
            $regionModel = Region::query()
                ->select($this->columns())
                ->with($this->detailRelations())
                ->withCount($this->counts())
                ->find($region);

            if (! $regionModel) {
                return ApiResponse::responseJson(false, 'Không tìm thấy khu vực', 404, null, 404);
            }

            return ApiResponse::responseJson(true, 'Chi tiết khu vực', 200, new RegionDetailResource($regionModel), 200);
        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e);

            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    // Cập nhật thông tin khu vực
    public function update(UpdateRequest $request, int $region): JsonResponse
    {
        try {
            $validated = $request->validated();
            $admin = $request->user('admin');

            if (! $admin || ! AdminScope::isSuperAdmin($admin)) {
                return ApiResponse::responseJson(false, 'Chỉ super admin mới được cập nhật khu vực', 403, null, 403);
            }

            $response = DB::transaction(function () use ($validated, $region, $admin, $request): JsonResponse {
                $regionModel = Region::query()->lockForUpdate()->find($region);

                if (! $regionModel) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy khu vực', 404, null, 404);
                }

                $oldData = $regionModel->toArray();
                $regionModel->fill($this->payload($validated, $regionModel->created_by, true))->save();

                if ($admin) {
                    AdminActivityLogger::write($admin, 'Cập nhật khu vực', Region::class, $regionModel->id, $oldData, $regionModel->fresh()->toArray(), $request);
                }

                $regionModel->load($this->storeRelations())->loadCount($this->counts());

                return ApiResponse::responseJson(true, 'Cập nhật khu vực thành công', 200, new RegionDetailResource($regionModel), 200);
            });

            return $response;
        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e);

            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    // Cập nhật trạng thái hoạt động của khu vực
    public function updateStatus(StatusRequest $request, int $region): JsonResponse
    {
        try {
            $validated = $request->validated();
            $admin = $request->user('admin');

            if (! $admin || ! AdminScope::isSuperAdmin($admin)) {
                return ApiResponse::responseJson(false, 'Chỉ super admin mới được đổi trạng thái khu vực', 403, null, 403);
            }

            $response = DB::transaction(function () use ($validated, $region, $admin, $request): JsonResponse {
                $regionModel = Region::query()->lockForUpdate()->find($region);

                if (! $regionModel) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy khu vực', 404, null, 404);
                }

                $oldData = $regionModel->toArray();
                $regionModel->forceFill(['is_active' => $validated['status']])->save();

                AdminActivityLogger::write($admin, 'Cập nhật trạng thái khu vực', Region::class, $regionModel->id, $oldData, $regionModel->fresh()->toArray(), $request);

                $regionModel->load($this->storeRelations())->loadCount($this->counts());

                return ApiResponse::responseJson(true, 'Cập nhật trạng thái khu vực thành công', 200, new RegionDetailResource($regionModel), 200);
            });

            return $response;
        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e);

            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    // Xóa khu vực khỏi hệ thống
    public function destroy(Request $request, int $region): JsonResponse
    {
        try {
            $admin = $request->user('admin');

            if (! $admin || ! AdminScope::isSuperAdmin($admin)) {
                return ApiResponse::responseJson(false, 'Chỉ super admin mới được xóa khu vực', 403, null, 403);
            }

            $response = DB::transaction(function () use ($region, $admin, $request): JsonResponse {
                $regionModel = Region::query()->withCount(['children', 'buildings'])->lockForUpdate()->find($region);

                if (! $regionModel) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy khu vực', 404, null, 404);
                }

                if ($regionModel->children_count > 0 || $regionModel->buildings_count > 0) {
                    return ApiResponse::responseJson(false, 'Không thể xóa khu vực đang có dữ liệu phụ thuộc', 422, null, 422);
                }

                $oldData = $regionModel->toArray();
                $regionModel->delete();

                if ($admin) {
                    AdminActivityLogger::write($admin, 'Xóa khu vực', Region::class, $regionModel->id, $oldData, null, $request);
                }

                return ApiResponse::responseJson(true, 'Xóa khu vực thành công', 200, null, 200);
            });

            return $response;
        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e);

            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    // Tạo cấu trúc dữ liệu lưu khu vực
    private function payload(array $validated, ?int $createdBy = null, bool $isUpdate = false): array
    {
        $payload = [
            'parent_id' => $validated['parent_id'] ?? null,
            'code' => $validated['code'] ?? null,
            'name' => $validated['name'] ?? null,
            'path' => $validated['path'] ?? null,
            'slug' => $validated['slug'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'] ?? $validated['status'] ?? true,
        ];

        if (! $isUpdate) {
            $payload['created_by'] = $createdBy;
        }

        return collect($payload)->filter(fn ($value): bool => $value !== null)->all();
    }

    // Danh sách cột cần lấy của khu vực
    private function columns(): array
    {
        return ['id', 'parent_id', 'code', 'name', 'path', 'slug', 'description', 'is_active', 'created_by', 'created_at', 'updated_at'];
    }

    // Các quan hệ liên kết của khu vực trong danh sách
    private function listRelations(): array
    {
        return ['parent:id,name'];
    }

    // Các quan hệ liên kết cần load sau khi tạo mới khu vực
    private function storeRelations(): array
    {
        return ['parent:id,name', 'children:id,parent_id,name'];
    }

    // Các quan hệ liên kết chi tiết khu vực
    private function detailRelations(): array
    {
        return ['parent:id,name', 'children:id,parent_id,code,name,path,slug,is_active', 'buildings:id,region_id,name,slug,address,status'];
    }

    // Các quan hệ cần đếm số lượng liên kết
    private function counts(): array
    {
        return ['children', 'buildings'];
    }

    // Tạo truy vấn danh sách khu vực
    private function queryRegions(array $validated): Builder
    {
        return Region::query()
            ->select($this->columns())
            ->with($this->listRelations())
            ->withCount($this->counts())
            ->when(isset($validated['status']), fn ($query) => $query->where('is_active', $validated['status']))
            ->orderByRaw('parent_id IS NOT NULL')
            ->orderBy('name');
    }

    // Tìm kiếm khu vực theo từ khóa
    private function searchRegions(string $keyword, array $validated): LengthAwarePaginator
    {
        $builder = Region::search($keyword);

        if (isset($validated['status'])) {
            $builder->where('is_active', (bool) $validated['status']);
        }

        return $builder
            ->query(fn ($query) => $query
                ->select($this->columns())
                ->with($this->listRelations())
                ->withCount($this->counts()))
            ->paginate($validated['per_page'] ?? 20);
    }
}
