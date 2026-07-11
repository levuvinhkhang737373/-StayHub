<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AdminActivityLogger;
use App\Helpers\AdminScope;
use App\Helpers\ApiResponse;
use App\Helpers\ImageHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Tenant\IndexRequest;
use App\Http\Requests\Admin\Tenant\RegisterRequest;
use App\Http\Requests\Admin\Tenant\StatusRequest;
use App\Http\Requests\Admin\Tenant\UpdateRequest;
use App\Http\Resources\Admin\TenantDetailResource;
use App\Http\Resources\Admin\TenantResource;
use App\Mail\SendTenantPasswordEmail;
use App\Models\Admin;
use App\Models\Building;
use App\Models\Contract;
use App\Models\Tenant;
use App\Support\BusinessRules\OperationalStateGuard;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TenantController extends Controller
{
    private const IMAGE_DISK = 's3';
    private const GENDER_POLICY_ERROR_MESSAGE = 'Giới tính khách thuê không phù hợp với chính sách giới tính của tòa nhà.';

    // Danh sách khách thuê
    public function index(IndexRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canManageTenants($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xem khách thuê', 403, null, 403);
            }

            $keyword = trim($validated['keyword'] ?? '');
            $tenants = $keyword !== ''
                ? $this->searchTenants($keyword, $validated, $admin)
                : $this->queryTenants($validated, $admin)->paginate($validated['per_page'] ?? 20);

            return ApiResponse::responseJson(true, 'Danh sách khách thuê', 200, $this->paginatedResource($tenants), 200);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    // Tạo mới khách thuê
    public function store(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $uploadedPaths = [];

        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canManageTenants($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền tạo khách thuê', 403, null, 403);
            }

            $plainPassword = $this->generateInitialPassword();
            $validated['password'] = $plainPassword;
            $validated['created_by'] = $admin->id;
            $validated['gender'] = (int) ($validated['gender'] ?? Tenant::GENDER_MALE);
            $validated['status'] = (int) ($validated['status'] ?? Tenant::STATUS_RENTING);
            $validated['identity_type'] = (int) ($validated['identity_type'] ?? Tenant::IDENTITY_TYPE_CCCD);

            $building = Building::query()->find((int) $validated['building_id']);

            if (! $building) {
                return ApiResponse::responseJson(false, 'Tòa nhà không tồn tại.', 422, null, 422);
            }

            $buildingStateError = OperationalStateGuard::tenantCreationBlockReason($building);
            if ($buildingStateError !== null) {
                return ApiResponse::responseJson(false, $buildingStateError, 422, null, 422);
            }

            if (! $this->buildingAllowsTenantGender((int) $validated['building_id'], $validated['gender'])) {
                return ApiResponse::responseJson(false, self::GENDER_POLICY_ERROR_MESSAGE, 422, null, 422);
            }

            $tenant = DB::transaction(function () use ($request, $validated, $admin, &$uploadedPaths): Tenant {
                $createdTenant = Tenant::query()->create($this->payload($validated));
                $imagePayload = $this->storeImages($request, $createdTenant, $uploadedPaths);

                if ($imagePayload !== []) {
                    $createdTenant->forceFill($imagePayload)->save();
                }

                AdminActivityLogger::write($admin, 'Tạo khách thuê', Tenant::class, $createdTenant->id, null, $createdTenant->fresh()->toArray(), $request);

                $this->loadDetailRelations($createdTenant);

                return $createdTenant;
            });

            $message = 'Tạo khách thuê thành công. Mật khẩu đang được gửi qua email.';

            try {
                Mail::to($tenant->email)->queue(new SendTenantPasswordEmail($tenant, $plainPassword));
            } catch (\Throwable $mailException) {
                Log::error('Không thể queue email mật khẩu khách thuê.', [
                    'tenant_id' => $tenant->id,
                    'email' => $tenant->email,
                    'error' => $mailException->getMessage(),
                ]);

                $message = 'Tạo khách thuê thành công nhưng chưa gửi được email mật khẩu.';
            }

            return ApiResponse::responseJson(true, $message, 201, new TenantDetailResource($tenant), 201);
        } catch (\Exception $e) {
            Log::error('Lỗi khi tạo khách thuê: ' . $e->getMessage(), [
                'exception' => $e,
                'validated' => $validated ?? null,
            ]);
            $this->deleteDiskImages($uploadedPaths);

            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    // Xem chi tiết khách thuê
    public function show(Request $request, int $tenant): JsonResponse
    {
        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canManageTenants($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xem khách thuê', 403, null, 403);
            }

            $tenantModel = $this->tenantQueryFor($admin)
                ->select($this->detailColumns())
                ->with($this->detailRelations())
                ->withCount($this->detailCounts())
                ->find($tenant);

            if (! $tenantModel) {
                return ApiResponse::responseJson(false, 'Không tìm thấy khách thuê', 404, null, 404);
            }

            return ApiResponse::responseJson(true, 'Chi tiết khách thuê', 200, new TenantDetailResource($tenantModel), 200);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    // Cập nhật thông tin khách thuê
    public function update(UpdateRequest $request, int $tenant): JsonResponse
    {
        $validated = $request->validated();
        $uploadedPaths = [];
        $pathsToDelete = [];

        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canManageTenants($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền cập nhật khách thuê', 403, null, 403);
            }

            $response = DB::transaction(function () use ($request, $validated, $tenant, $admin, &$uploadedPaths, &$pathsToDelete): JsonResponse {
                $tenantModel = $this->tenantQueryFor($admin)->lockForUpdate()->find($tenant);

                if (! $tenantModel) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy khách thuê', 404, null, 404);
                }

                $oldData = $tenantModel->toArray();
                $payload = $this->payload($validated, true);

                if (array_key_exists('status', $payload)) {
                    $stateError = OperationalStateGuard::tenantStatusBlockReason($tenantModel, (int) $payload['status']);

                    if ($stateError !== null) {
                        return ApiResponse::responseJson(false, $stateError, 422, null, 422);
                    }
                }

                if (array_key_exists('gender', $payload)) {
                    $genderError = OperationalStateGuard::tenantGenderBlockReason($tenantModel, $payload['gender'] === null ? null : (int) $payload['gender']);

                    if ($genderError !== null) {
                        return ApiResponse::responseJson(false, $genderError, 422, null, 422);
                    }
                }

                $imagePayload = $this->storeImages($request, $tenantModel, $uploadedPaths);
                $pathsToDelete = $this->collectOldImagesForDeletion($tenantModel, $request, $imagePayload);
                $payload = array_merge($payload, $imagePayload, $this->nullDeletedImages($request, $imagePayload));

                if ($payload !== []) {
                    $tenantModel->fill($payload)->save();
                }

                $newData = $tenantModel->fresh()->toArray();
                AdminActivityLogger::write($admin, 'Cập nhật khách thuê', Tenant::class, $tenantModel->id, $oldData, $newData, $request);

                $this->loadDetailRelations($tenantModel);

                return ApiResponse::responseJson(true, 'Cập nhật khách thuê thành công', 200, new TenantDetailResource($tenantModel), 200);
            });

            $this->deleteDiskImages($pathsToDelete);

            return $response;
        } catch (\Exception $e) {
            $this->deleteDiskImages($uploadedPaths);

            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    // Cập nhật trạng thái tài khoản của khách thuê
    public function updateStatus(StatusRequest $request, int $tenant): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canManageTenants($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền đổi trạng thái khách thuê', 403, null, 403);
            }

            $response = DB::transaction(function () use ($validated, $tenant, $admin, $request): JsonResponse {
                $tenantModel = $this->tenantQueryFor($admin)->lockForUpdate()->find($tenant);

                if (! $tenantModel) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy khách thuê', 404, null, 404);
                }

                $oldData = $tenantModel->toArray();
                $stateError = OperationalStateGuard::tenantStatusBlockReason($tenantModel, (int) $validated['status']);

                if ($stateError !== null) {
                    return ApiResponse::responseJson(false, $stateError, 422, null, 422);
                }

                $tenantModel->forceFill(['status' => (int) $validated['status']])->save();
                $newData = $tenantModel->fresh()->toArray();

                if (filled($validated['reason'] ?? null)) {
                    $newData['reason'] = $validated['reason'];
                }

                AdminActivityLogger::write($admin, 'Cập nhật trạng thái khách thuê', Tenant::class, $tenantModel->id, $oldData, $newData, $request);

                $this->loadDetailRelations($tenantModel);

                return ApiResponse::responseJson(true, 'Cập nhật trạng thái khách thuê thành công', 200, new TenantDetailResource($tenantModel), 200);
            });

            return $response;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    // Xóa khách thuê
    public function destroy(Request $request, int $tenant): JsonResponse
    {
        $pathsToDelete = [];

        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canManageTenants($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xóa khách thuê', 403, null, 403);
            }

            $response = DB::transaction(function () use ($tenant, $admin, $request, &$pathsToDelete): JsonResponse {
                $tenantModel = $this->tenantQueryFor($admin)
                    ->withCount($this->deleteCounts())
                    ->lockForUpdate()
                    ->find($tenant);

                if (! $tenantModel) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy khách thuê', 404, null, 404);
                }

                if ($tenantModel->status !== Tenant::STATUS_STOPPED_RENTING) {
                    return ApiResponse::responseJson(false, 'Chỉ có thể xóa khách thuê đã ngừng thuê', 422, null, 422);
                }

                if ($this->hasRelatedData($tenantModel)) {
                    return ApiResponse::responseJson(false, 'Không thể xóa khách thuê đã phát sinh dữ liệu liên quan. Vui lòng chuyển sang ngừng thuê.', 422, null, 422);
                }

                $oldData = $tenantModel->toArray();
                $pathsToDelete = $this->currentImagePaths($tenantModel);
                $tenantModel->delete();

                AdminActivityLogger::write($admin, 'Xóa khách thuê', Tenant::class, $tenantModel->id, $oldData, null, $request);

                return ApiResponse::responseJson(true, 'Xóa khách thuê thành công', 200, null, 200);
            });

            $this->deleteDiskImages($pathsToDelete);

            return $response;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    // Kiểm tra quyền quản lý khách thuê của admin
    private function canManageTenants(Admin $admin): bool
    {
        return AdminScope::isSuperAdmin($admin) || AdminScope::isBuildingManager($admin);
    }

    // Định dạng dữ liệu khách thuê phân trang
    private function paginatedResource(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => TenantResource::collection($paginator->items())->resolve(),
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'path' => $paginator->path(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
        ];
    }

    // Tạo cấu trúc dữ liệu lưu thông tin khách thuê
    private function payload(array $validated, bool $isUpdate = false): array
    {
        $payload = [];
        $fields = ['building_id', 'full_name', 'gender', 'date_of_birth', 'phone', 'email', 'username', 'password', 'permanent_address', 'current_address', 'status', 'identity_type', 'identity_number', 'front_image_url', 'back_image_url'];

        if (! $isUpdate) {
            $fields[] = 'created_by';
        }

        foreach ($fields as $field) {
            if (! array_key_exists($field, $validated)) {
                continue;
            }

            if ($field === 'password' && ! filled($validated[$field])) {
                continue;
            }

            $payload[$field] = $validated[$field];
        }

        return $payload;
    }

    // Tạo mật khẩu ngẫu nhiên ban đầu cho khách thuê
    private function generateInitialPassword(): string
    {
        return 'stayhub'.str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    // Tạo truy vấn danh sách khách thuê
    private function queryTenants(array $validated, Admin $admin): Builder
    {
        $query = $this->tenantQueryFor($admin)
            ->select($this->listColumns())
            ->with($this->listRelations())
            ->withCount($this->listCounts())
            ->when(isset($validated['status']), fn (Builder $q): Builder => $q->where('status', (int) $validated['status']))
            ->when($this->requiresActiveCurrentRoom($validated), fn (Builder $q): Builder => $this->filterHasActiveCurrentRoom($q))
            ->when(isset($validated['gender']), fn (Builder $q): Builder => $q->where('gender', (int) $validated['gender']))
            ->when(isset($validated['identity_type']), fn (Builder $q): Builder => $q->where('identity_type', (int) $validated['identity_type']));

        $withoutReservedContract = isset($validated['without_reserved_contract']) && filter_var($validated['without_reserved_contract'], FILTER_VALIDATE_BOOLEAN);
        $withoutActiveContract = isset($validated['without_active_contract']) && filter_var($validated['without_active_contract'], FILTER_VALIDATE_BOOLEAN);
        $isContractSearch = $withoutActiveContract || $withoutReservedContract;

        if ($isContractSearch) {
            if (!isset($validated['building_id'])) {
                return $query->whereRaw('1 = 0');
            }

            $buildingId = (int) $validated['building_id'];
            $query->where(function (Builder $q) use ($buildingId): void {
                $q->where('building_id', $buildingId)
                    ->orWhereNull('building_id');
            });

            $query->where('status', Tenant::STATUS_RENTING)
                ->whereDoesntHave('contracts', function (Builder $q) use ($withoutReservedContract): void {
                    $withoutReservedContract
                        ? $q->whereIn('status', Contract::RESERVED_STATUSES)
                        : $q->where('status', Contract::STATUS_ACTIVE);
                });

            $this->applyBuildingGenderPolicyFilter($query, $buildingId);
        } elseif ($this->requiresActiveCurrentRoom($validated) && isset($validated['building_id'])) {
            $this->filterActiveCurrentRoomBuilding($query, (int) $validated['building_id']);
        } else {
            $query->when(isset($validated['building_id']), fn (Builder $q): Builder => $q->where('building_id', (int) $validated['building_id']));
        }

        return $query
            ->when(AdminScope::isSuperAdmin($admin) && isset($validated['created_by']), fn (Builder $q): Builder => $q->where('created_by', (int) $validated['created_by']))
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    // Tìm kiếm khách thuê theo từ khóa
    private function searchTenants(string $keyword, array $validated, Admin $admin): LengthAwarePaginator
    {
        $isContractSearch = (isset($validated['without_active_contract']) && filter_var($validated['without_active_contract'], FILTER_VALIDATE_BOOLEAN))
            || (isset($validated['without_reserved_contract']) && filter_var($validated['without_reserved_contract'], FILTER_VALIDATE_BOOLEAN));

        if (AdminScope::isBuildingManager($admin) || $isContractSearch || $this->requiresActiveCurrentRoom($validated)) {
            return $this->applyKeywordFilter($this->queryTenants($validated, $admin), $keyword)
                ->paginate($validated['per_page'] ?? 20);
        }

        $builder = Tenant::search($keyword);

        foreach (['status', 'gender', 'identity_type', 'building_id'] as $field) {
            if (isset($validated[$field])) {
                $builder->where($field, (int) $validated[$field]);
            }
        }

        if (AdminScope::isSuperAdmin($admin) && isset($validated['created_by'])) {
            $builder->where('created_by', (int) $validated['created_by']);
        }

        return $builder
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->query(fn ($query) => $this->applyTenantScope($query, $admin)
                ->select($this->listColumns())
                ->with($this->listRelations())
                ->withCount($this->listCounts()))
            ->paginate($validated['per_page'] ?? 20);
    }

    // Bộ lọc tìm kiếm khách thuê theo từ khóa
    private function applyKeywordFilter(Builder $query, string $keyword): Builder
    {
        $likeKeyword = '%'.addcslashes($keyword, '\\%_').'%';

        return $query->where(function (Builder $keywordQuery) use ($likeKeyword): void {
            foreach (['full_name', 'username', 'phone', 'email', 'identity_number', 'permanent_address', 'current_address'] as $column) {
                $keywordQuery->orWhere($column, 'like', $likeKeyword);
            }
        });
    }

    // Kiểm tra bộ lọc có yêu cầu khách thuê đang có phòng hoạt động không
    private function requiresActiveCurrentRoom(array $validated): bool
    {
        return isset($validated['with_active_current_room'])
            && filter_var($validated['with_active_current_room'], FILTER_VALIDATE_BOOLEAN);
    }

    // Lọc danh sách khách thuê đang có phòng ở hoạt động
    private function filterHasActiveCurrentRoom(Builder $query): Builder
    {
        return $query->whereHas('contractTenants', fn (Builder $contractTenantQuery): Builder => $contractTenantQuery
            ->where('is_staying', true)
            ->whereNull('leave_date')
            ->whereHas('contract', fn (Builder $contractQuery): Builder => $contractQuery
                ->where('status', Contract::STATUS_ACTIVE)
                ->whereHas('room')));
    }

    // Lọc khách thuê đang ở tại tòa nhà cụ thể
    private function filterActiveCurrentRoomBuilding(Builder $query, int $buildingId): Builder
    {
        return $query->whereHas('contractTenants', fn (Builder $contractTenantQuery): Builder => $contractTenantQuery
            ->where('is_staying', true)
            ->whereNull('leave_date')
            ->whereHas('contract', fn (Builder $contractQuery): Builder => $contractQuery
                ->where('status', Contract::STATUS_ACTIVE)
                ->whereHas('room', fn (Builder $roomQuery): Builder => $roomQuery->where('building_id', $buildingId))));
    }

    // Bộ lọc chính sách giới tính của tòa nhà đối với khách thuê
    private function applyBuildingGenderPolicyFilter(Builder $query, int $buildingId): void
    {
        $building = Building::query()->select(['id', 'gender_policy', 'status'])->find($buildingId);

        if (! $building) {
            $query->whereRaw('1 = 0');

            return;
        }

        match ((int) $building->gender_policy) {
            Building::GENDER_POLICY_MALE => $query->where('gender', Tenant::GENDER_MALE),
            Building::GENDER_POLICY_FEMALE => $query->where('gender', Tenant::GENDER_FEMALE),
            Building::GENDER_POLICY_MIXED => null,
            default => $query->whereRaw('1 = 0'),
        };
    }

    // Kiểm tra tòa nhà có chấp nhận giới tính của khách thuê không
    private function buildingAllowsTenantGender(?int $buildingId, ?int $tenantGender): bool
    {
        if (! $buildingId) {
            return true;
        }

        $building = Building::query()->select(['id', 'gender_policy', 'status'])->find($buildingId);

        return $building?->allowsTenantGender($tenantGender) ?? false;
    }

    // Tạo truy vấn tìm kiếm khách thuê
    private function tenantQueryFor(Admin $admin): Builder
    {
        return $this->applyTenantScope(Tenant::query(), $admin);
    }

    // Áp dụng phạm vi truy cập dữ liệu khách thuê cho admin
    private function applyTenantScope(Builder $query, Admin $admin): Builder
    {
        return AdminScope::applyTenantScope($query, $admin);
    }

    // Lưu trữ hình ảnh chân dung/CCCD của khách thuê
    private function storeImages(Request $request, Tenant $tenant, array &$uploadedPaths): array
    {
        $images = [];

        foreach ($this->imageFields() as $requestKey => $column) {
            if (! $request->hasFile($requestKey)) {
                continue;
            }

            $images[$column] = ImageHelper::storeOnDisk($request->file($requestKey), $this->imageFolder($tenant, $requestKey), self::IMAGE_DISK);
            $uploadedPaths[] = $images[$column];
        }

        return $images;
    }

    // Gom danh sách ảnh cũ cần xóa khi cập nhật
    private function collectOldImagesForDeletion(Tenant $tenant, Request $request, array $imagePayload): array
    {
        $paths = [];

        foreach ($this->imageFields() as $requestKey => $column) {
            if (array_key_exists($column, $imagePayload) || $request->boolean('delete_'.$requestKey)) {
                $paths[] = $tenant->{$column};
            }
        }

        return array_values(array_filter($paths));
    }

    // Xử lý giá trị rỗng cho các ảnh đã xóa
    private function nullDeletedImages(Request $request, array $imagePayload): array
    {
        $payload = [];

        foreach ($this->imageFields() as $requestKey => $column) {
            if (! array_key_exists($column, $imagePayload) && $request->boolean('delete_'.$requestKey)) {
                $payload[$column] = null;
            }
        }

        return $payload;
    }

    // Danh sách các trường lưu trữ đường dẫn ảnh của khách thuê
    private function imageFields(): array
    {
        return [
            'front_image' => 'front_image_url',
            'back_image' => 'back_image_url',
        ];
    }

    // Thư mục lưu trữ hình ảnh của khách thuê
    private function imageFolder(Tenant $tenant, string $requestKey): string
    {
        $folder = match ($requestKey) {
            'front_image' => 'identity/front',
            'back_image' => 'identity/back',
            default => 'images',
        };

        return 'tenants/'.$tenant->id.'/'.$folder;
    }

    // Xóa ảnh vật lý khỏi đĩa lưu trữ
    private function deleteDiskImages(array $paths): void
    {
        collect($paths)
            ->filter()
            ->unique()
            ->each(fn (string $path): bool => ImageHelper::deleteFromDisk($path, self::IMAGE_DISK));
    }

    // Lấy đường dẫn các ảnh hiện tại của khách thuê
    private function currentImagePaths(Tenant $tenant): array
    {
        return collect(['avatar_url', 'front_image_url', 'back_image_url'])
            ->map(fn (string $column): ?string => $tenant->{$column})
            ->filter()
            ->values()
            ->all();
    }

    // Kiểm tra khách thuê có dữ liệu liên quan phát sinh không
    private function hasRelatedData(Tenant $tenant): bool
    {
        return collect($this->deleteCountColumns())
            ->sum(fn (string $column): int => (int) $tenant->{$column}) > 0;
    }

    // Nạp chi tiết các quan hệ liên kết của khách thuê
    private function loadDetailRelations(Tenant $tenant): void
    {
        $tenant->load($this->detailRelations());
        $tenant->loadCount($this->detailCounts());
    }

    // Cột hiển thị trong danh sách khách thuê
    private function listColumns(): array
    {
        return ['id', 'created_by', 'building_id', 'username', 'full_name', 'phone', 'email', 'avatar_url', 'date_of_birth', 'gender', 'status', 'identity_type', 'identity_number', 'front_image_url', 'back_image_url', 'created_at', 'updated_at'];
    }

    // Cột hiển thị trong chi tiết khách thuê
    private function detailColumns(): array
    {
        return ['id', 'created_by', 'building_id', 'username', 'full_name', 'phone', 'email', 'avatar_url', 'date_of_birth', 'gender', 'permanent_address', 'current_address', 'status', 'identity_type', 'identity_number', 'front_image_url', 'back_image_url', 'created_at', 'updated_at'];
    }

    // Truy vấn quan hệ khách thuê đang tham gia hợp đồng hiệu lực
    private function currentContractTenantQuery(HasMany $query): HasMany
    {
        return $query
            ->select(['id', 'contract_id', 'tenant_id', 'join_date', 'leave_date', 'is_staying'])
            ->where('is_staying', true)
            ->whereHas('contract', fn (Builder $contractQuery): Builder => $contractQuery->where('status', Contract::STATUS_ACTIVE))
            ->orderByDesc('join_date')
            ->orderByDesc('id');
    }

    // Các quan hệ liên kết hiển thị ở danh sách
    private function listRelations(): array
    {
        return [
            'creator:id,username,full_name,email,role',
            'building:id,name,slug,status',
            'contractTenants' => fn (HasMany $query): HasMany => $this->currentContractTenantQuery($query),
            'contractTenants.contract:id,contract_code,room_id,status,start_date,end_date',
            'contractTenants.contract.room:id,building_id,room_number,slug,status',
            'contractTenants.contract.room.building:id,name,slug,status',
        ];
    }

    // Các quan hệ liên kết hiển thị ở chi tiết
    private function detailRelations(): array
    {
        return [
            'creator:id,username,full_name,email,phone,role,status',
            'building:id,name,slug,status',
            'contractTenants' => fn (HasMany $query): HasMany => $this->currentContractTenantQuery($query),
            'contractTenants.contract:id,contract_code,room_id,status,start_date,end_date,room_price,deposit_amount,payment_status',
            'contractTenants.contract.room:id,building_id,room_number,slug,status',
            'contractTenants.contract.room.building:id,name,slug,status',
            'contractTenants.contract.depositTransactions:id,contract_id,transaction_type,amount',
        ];
    }

    // Đếm số lượng quan hệ ở danh sách
    private function listCounts(): array
    {
        return [
            'vehicles',
        ];
    }

    // Đếm số lượng quan hệ ở chi tiết
    private function detailCounts(): array
    {
        return array_merge($this->listCounts(), [
            'notificationReads',
        ]);
    }

    // Đếm các quan hệ dữ liệu liên quan trước khi xóa
    private function deleteCounts(): array
    {
        return [
            'contractTenants',
            'roomMovements',
            'vehicles',
            'maintenanceRequests',
            'maintenanceFeedbacks',
            'notificationReads',
        ];
    }

    // Danh sách tên cột đếm dữ liệu liên quan trước khi xóa
    private function deleteCountColumns(): array
    {
        return collect($this->deleteCounts())
            ->map(fn (string $relation): string => strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $relation)).'_count')
            ->all();
    }
}
