<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AdminActivityLogger;
use App\Helpers\AdminScope;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminAccount\IndexRequest;
use App\Http\Requests\Admin\AdminAccount\RegisterRequest;
use App\Http\Requests\Admin\AdminAccount\StatusRequest;
use App\Http\Requests\Admin\AdminAccount\UpdateRequest;
use App\Http\Resources\Admin\AdminAccountDetailResource;
use App\Http\Resources\Admin\AdminAccountResource;
use App\Mail\SendPasswordEmail;
use App\Models\Admin;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AdminAccountController extends Controller
{
    public function index(IndexRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canManageAdminAccounts($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xem tài khoản admin', 403, null, 403);
            }

            $accounts = $this->queryAccounts($validated)->paginate($validated['per_page'] ?? 20);

            return ApiResponse::responseJson(true, 'Danh sách tài khoản admin', 200, $this->paginatedResource($accounts), 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function store(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canManageAdminAccounts($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền tạo tài khoản admin', 403, null, 403);
            }

            $plainPassword = $this->generateInitialPassword();
            $validated['password'] = $plainPassword;

            $account = DB::transaction(function () use ($validated, $admin, $request): Admin {
                $createdAccount = Admin::query()->create($this->payload($validated));

                AdminActivityLogger::write($admin, 'create_admin_account', Admin::class, $createdAccount->id, null, $createdAccount->toArray(), $request);

                $this->loadDetailRelations($createdAccount);

                return $createdAccount;
            });

            $message = 'Tạo tài khoản admin thành công. Mật khẩu đang được gửi qua email.';

            try {
                Mail::to($account->email)->queue(new SendPasswordEmail($account, $plainPassword));
            } catch (\Throwable $mailException) {
                Log::error('Không thể queue email mật khẩu admin.', [
                    'admin_id' => $account->id,
                    'email' => $account->email,
                    'error' => $mailException->getMessage(),
                ]);

                $message = 'Tạo tài khoản admin thành công nhưng chưa gửi được email mật khẩu.';
            }

            return ApiResponse::responseJson(true, $message, 201, new AdminAccountDetailResource($account), 201);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function show(Request $request, int $account): JsonResponse
    {
        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canManageAdminAccounts($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xem tài khoản admin', 403, null, 403);
            }

            $accountModel = Admin::query()
                ->select($this->detailColumns())
                ->with($this->managedBuildingRelation())
                ->withCount($this->detailCounts())
                ->find($account);

            if (! $accountModel) {
                return ApiResponse::responseJson(false, 'Không tìm thấy tài khoản admin', 404, null, 404);
            }

            return ApiResponse::responseJson(true, 'Chi tiết tài khoản admin', 200, new AdminAccountDetailResource($accountModel), 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function update(UpdateRequest $request, int $account): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canManageAdminAccounts($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền cập nhật tài khoản admin', 403, null, 403);
            }

            $response = DB::transaction(function () use ($validated, $account, $admin, $request): JsonResponse {
                $accountModel = Admin::query()->lockForUpdate()->find($account);

                if (! $accountModel) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy tài khoản admin', 404, null, 404);
                }

                if ($accountModel->id === $admin->id && isset($validated['role']) && (int) $validated['role'] !== Admin::ROLE_SUPER_ADMIN) {
                    return ApiResponse::responseJson(false, 'Không thể tự hạ quyền tài khoản đang đăng nhập', 422, null, 422);
                }

                if ($this->wouldRemoveLastActiveSuperAdmin($accountModel, $validated)) {
                    return ApiResponse::responseJson(false, 'Không thể làm mất quản trị tổng hoạt động cuối cùng', 422, null, 422);
                }

                $oldData = $accountModel->toArray();
                $passwordChanged = filled($validated['password'] ?? null);
                $accountModel->fill($this->payload($validated, true))->save();

                $newData = $accountModel->fresh()->toArray();

                if ($passwordChanged) {
                    $newData['password_changed'] = true;
                }

                AdminActivityLogger::write($admin, 'update_admin_account', Admin::class, $accountModel->id, $oldData, $newData, $request);

                $this->loadDetailRelations($accountModel);

                return ApiResponse::responseJson(true, 'Cập nhật tài khoản admin thành công', 200, new AdminAccountDetailResource($accountModel), 200);
            });
            return $response;
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function updateStatus(StatusRequest $request, int $account): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canManageAdminAccounts($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền đổi trạng thái tài khoản admin', 403, null, 403);
            }

            $response = DB::transaction(function () use ($validated, $account, $admin, $request): JsonResponse {
                $accountModel = Admin::query()->lockForUpdate()->find($account);

                if (! $accountModel) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy tài khoản admin', 404, null, 404);
                }

                $nextStatus = (int) $validated['status'];

                if ($accountModel->id === $admin->id && $nextStatus !== Admin::STATUS_ACTIVE) {
                    return ApiResponse::responseJson(false, 'Không thể tự khóa tài khoản đang đăng nhập', 422, null, 422);
                }

                if ($accountModel->role === Admin::ROLE_SUPER_ADMIN && $accountModel->status === Admin::STATUS_ACTIVE && $nextStatus !== Admin::STATUS_ACTIVE && $this->activeSuperAdminsCount() <= 1) {
                    return ApiResponse::responseJson(false, 'Không thể khóa quản trị tổng hoạt động cuối cùng', 422, null, 422);
                }

                $oldData = $accountModel->toArray();
                $accountModel->forceFill(['status' => $nextStatus])->save();
                $newData = $accountModel->fresh()->toArray();

                if (filled($validated['reason'] ?? null)) {
                    $newData['reason'] = $validated['reason'];
                }

                AdminActivityLogger::write($admin, 'update_admin_account_status', Admin::class, $accountModel->id, $oldData, $newData, $request);

                $this->loadDetailRelations($accountModel);

                return ApiResponse::responseJson(true, 'Cập nhật trạng thái tài khoản admin thành công', 200, new AdminAccountDetailResource($accountModel), 200);
            });

            return $response;
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function destroy(Request $request, int $account): JsonResponse
    {
        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canManageAdminAccounts($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xóa tài khoản admin', 403, null, 403);
            }

            $response = DB::transaction(function () use ($account, $admin, $request): JsonResponse {
                $accountModel = Admin::query()->withCount($this->deleteCounts())->lockForUpdate()->find($account);

                if (! $accountModel) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy tài khoản admin', 404, null, 404);
                }

                if ($accountModel->id === $admin->id) {
                    return ApiResponse::responseJson(false, 'Không thể tự xóa tài khoản đang đăng nhập', 422, null, 422);
                }

                if ($accountModel->status === Admin::STATUS_ACTIVE) {
                    return ApiResponse::responseJson(false, 'Chỉ có thể xóa tài khoản admin đã ngừng hoạt động', 422, null, 422);
                }

                if ($accountModel->role === Admin::ROLE_SUPER_ADMIN && $this->activeSuperAdminsCount() <= 1) {
                    return ApiResponse::responseJson(false, 'Không thể xóa quản trị tổng cuối cùng', 422, null, 422);
                }

                if ($this->hasRelatedData($accountModel)) {
                    return ApiResponse::responseJson(false, 'Không thể xóa tài khoản đã phát sinh dữ liệu. Vui lòng chuyển sang ngừng hoạt động.', 422, null, 422);
                }

                $oldData = $accountModel->toArray();
                $accountModel->delete();

                AdminActivityLogger::write($admin, 'delete_admin_account', Admin::class, $accountModel->id, $oldData, null, $request);

                return ApiResponse::responseJson(true, 'Xóa tài khoản admin thành công', 200, null, 200);
            });

            return $response;
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    private function canManageAdminAccounts(Admin $admin): bool
    {
        return AdminScope::isSuperAdmin($admin);
    }

    private function paginatedResource(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => AdminAccountResource::collection($paginator->items())->resolve(),
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

    private function payload(array $validated, bool $isUpdate = false): array
    {
        $payload = [];
        $fields = ['username', 'full_name', 'email', 'phone', 'password', 'role', 'gender', 'address', 'avatar_url'];

        foreach ($fields as $field) {
            if (! array_key_exists($field, $validated)) {
                continue;
            }

            if ($field === 'password' && ! filled($validated[$field])) {
                continue;
            }

            if ($field === 'gender' && $validated[$field] === null) {
                continue;
            }

            $payload[$field] = $validated[$field];
        }

        if (! $isUpdate) {
            $payload['status'] = (int) ($validated['status'] ?? Admin::STATUS_ACTIVE);
        }

        return $payload;
    }

    private function generateInitialPassword(): string
    {
        return 'stayhub'.str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function queryAccounts(array $validated): Builder
    {
        $keyword = trim($validated['keyword'] ?? '');

        return Admin::query()
            ->select($this->listColumns())
            ->with($this->managedBuildingRelation())
            ->withCount($this->listCounts())
            ->when($keyword !== '', function (Builder $query) use ($keyword): Builder {
                return $query->where(function (Builder $innerQuery) use ($keyword): void {
                    $innerQuery->where('username', 'like', "%{$keyword}%")
                        ->orWhere('full_name', 'like', "%{$keyword}%")
                        ->orWhere('email', 'like', "%{$keyword}%")
                        ->orWhere('phone', 'like', "%{$keyword}%");
                });
            })
            ->when(isset($validated['role']), fn (Builder $query): Builder => $query->where('role', (int) $validated['role']))
            ->when(isset($validated['status']), fn (Builder $query): Builder => $query->where('status', (int) $validated['status']))
            ->latest('id');
    }

    private function wouldRemoveLastActiveSuperAdmin(Admin $account, array $validated): bool
    {
        if (! isset($validated['role']) || (int) $validated['role'] === Admin::ROLE_SUPER_ADMIN) {
            return false;
        }

        return $account->role === Admin::ROLE_SUPER_ADMIN
            && $account->status === Admin::STATUS_ACTIVE
            && $this->activeSuperAdminsCount() <= 1;
    }

    private function activeSuperAdminsCount(): int
    {
        return Admin::query()
            ->where('role', Admin::ROLE_SUPER_ADMIN)
            ->where('status', Admin::STATUS_ACTIVE)
            ->count();
    }

    private function hasRelatedData(Admin $account): bool
    {
        return collect($this->deleteCountColumns())
            ->sum(fn (string $column): int => (int) $account->{$column}) > 0;
    }

    private function listColumns(): array
    {
        return ['id', 'username', 'full_name', 'email', 'phone', 'avatar_url', 'image_path_faceid', 'role', 'status', 'gender', 'address', 'created_at', 'updated_at'];
    }

    private function managedBuildingRelation(): array
    {
        return ['managedBuildings:id,manager_admin_id,name,slug,address,status'];
    }

    private function loadDetailRelations(Admin $account): void
    {
        $account->load($this->managedBuildingRelation());
        $account->loadCount($this->detailCounts());
    }

    private function detailColumns(): array
    {
        return ['id', 'username', 'full_name', 'email', 'phone', 'avatar_url', 'image_path_faceid', 'created_faceid_at', 'updated_faceid_at', 'role', 'status', 'gender', 'address', 'created_at', 'updated_at'];
    }

    private function listCounts(): array
    {
        return ['managedBuildings', 'logs'];
    }

    private function detailCounts(): array
    {
        return ['managedBuildings', 'createdRegions', 'createdBuildings', 'createdRoomTypes', 'createdRooms', 'createdAssetTemplates', 'createdServices', 'settings', 'logs'];
    }

    private function deleteCounts(): array
    {
        return [
            'createdRegions',
            'managedBuildings',
            'createdBuildings',
            'uploadedBuildingImages',
            'createdRoomTypes',
            'createdRooms',
            'uploadedRoomImages',
            'createdAssetTemplates',
            'createdServices',
            'createdContracts',
            'createdContractTenants',
            'createdRoomMovements',
            'depositTransactions',
            'meterReadings',
            'invoices',
            'collectedPayments',
            'assignedMaintenanceRequests',
            'maintenanceRequestLogs',
            'notifications',
            'settings',
            'createdExpenseCategories',
            'expenses',
            'logs',
        ];
    }

    private function deleteCountColumns(): array
    {
        return collect($this->deleteCounts())
            ->map(fn (string $relation): string => strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $relation)).'_count')
            ->all();
    }
}
