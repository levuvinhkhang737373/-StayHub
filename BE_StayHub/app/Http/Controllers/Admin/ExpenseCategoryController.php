<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AdminActivityLogger;
use App\Helpers\AdminScope;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ExpenseCategory\IndexRequest;
use App\Http\Requests\Admin\ExpenseCategory\RegisterRequest;
use App\Http\Requests\Admin\ExpenseCategory\StatusRequest;
use App\Http\Requests\Admin\ExpenseCategory\UpdateRequest;
use App\Http\Resources\Admin\ExpenseCategoryDetailResource;
use App\Http\Resources\Admin\ExpenseCategoryResource;
use App\Models\Admin;
use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExpenseCategoryController extends Controller
{
    // Danh sách danh mục chi phí
    public function index(IndexRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canViewExpenseCategories($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xem danh mục chi phí', 403, null, 403);
            }

            $expenseCategories = $this->queryExpenseCategories($validated)->paginate($validated['per_page'] ?? 20);

            return ApiResponse::responseJson(true, 'Danh sách danh mục chi phí', 200, ExpenseCategoryResource::collection($expenseCategories), 200);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    // Tạo mới danh mục chi phí
    public function store(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canMutateExpenseCategories($admin)) {
                return ApiResponse::responseJson(false, 'Chỉ super admin mới được tạo danh mục chi phí', 403, null, 403);
            }

            $response = DB::transaction(function () use ($validated, $admin, $request): JsonResponse {
                $expenseCategory = ExpenseCategory::query()->create($this->payload($validated, $admin->id));

                AdminActivityLogger::write($admin, 'Tạo danh mục chi phí', ExpenseCategory::class, $expenseCategory->id, null, $expenseCategory->toArray(), $request);

                $expenseCategory->load($this->relations())->loadCount($this->counts());

                return ApiResponse::responseJson(true, 'Tạo danh mục chi phí thành công', 201, new ExpenseCategoryDetailResource($expenseCategory), 201);
            });

            return $response;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    // Xem chi tiết danh mục chi phí
    public function show(Request $request, int $expenseCategory): JsonResponse
    {
        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canViewExpenseCategories($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xem danh mục chi phí', 403, null, 403);
            }

            $expenseCategoryModel = ExpenseCategory::query()
                ->select($this->columns())
                ->with($this->relations())
                ->withCount($this->counts())
                ->find($expenseCategory);

            if (! $expenseCategoryModel) {
                return ApiResponse::responseJson(false, 'Không tìm thấy danh mục chi phí', 404, null, 404);
            }

            return ApiResponse::responseJson(true, 'Chi tiết danh mục chi phí', 200, new ExpenseCategoryDetailResource($expenseCategoryModel), 200);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    // Cập nhật thông tin danh mục chi phí
    public function update(UpdateRequest $request, int $expenseCategory): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canMutateExpenseCategories($admin)) {
                return ApiResponse::responseJson(false, 'Chỉ super admin mới được cập nhật danh mục chi phí', 403, null, 403);
            }

            $response = DB::transaction(function () use ($validated, $expenseCategory, $admin, $request): JsonResponse {
                $expenseCategoryModel = ExpenseCategory::query()->lockForUpdate()->find($expenseCategory);

                if (! $expenseCategoryModel) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy danh mục chi phí', 404, null, 404);
                }

                $oldData = $expenseCategoryModel->toArray();
                $expenseCategoryModel->fill($this->payload($validated, null, true))->save();

                AdminActivityLogger::write($admin, 'Cập nhật danh mục chi phí', ExpenseCategory::class, $expenseCategoryModel->id, $oldData, $expenseCategoryModel->fresh()->toArray(), $request);

                $expenseCategoryModel->load($this->relations())->loadCount($this->counts());

                return ApiResponse::responseJson(true, 'Cập nhật danh mục chi phí thành công', 200, new ExpenseCategoryDetailResource($expenseCategoryModel), 200);
            });

            return $response;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    // Cập nhật trạng thái hoạt động của danh mục chi phí
    public function updateStatus(StatusRequest $request, int $expenseCategory): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canMutateExpenseCategories($admin)) {
                return ApiResponse::responseJson(false, 'Chỉ super admin mới được đổi trạng thái danh mục chi phí', 403, null, 403);
            }

            $response = DB::transaction(function () use ($validated, $expenseCategory, $admin, $request): JsonResponse {
                $expenseCategoryModel = ExpenseCategory::query()->lockForUpdate()->find($expenseCategory);

                if (! $expenseCategoryModel) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy danh mục chi phí', 404, null, 404);
                }

                $oldData = $expenseCategoryModel->toArray();
                $expenseCategoryModel->forceFill(['is_active' => (bool) $validated['status']])->save();

                AdminActivityLogger::write($admin, 'Cập nhật trạng thái danh mục chi phí', ExpenseCategory::class, $expenseCategoryModel->id, $oldData, $expenseCategoryModel->fresh()->toArray(), $request);

                $expenseCategoryModel->load($this->relations())->loadCount($this->counts());

                return ApiResponse::responseJson(true, 'Cập nhật trạng thái danh mục chi phí thành công', 200, new ExpenseCategoryDetailResource($expenseCategoryModel), 200);
            });

            return $response;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    // Xóa danh mục chi phí
    public function destroy(Request $request, int $expenseCategory): JsonResponse
    {
        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canMutateExpenseCategories($admin)) {
                return ApiResponse::responseJson(false, 'Chỉ super admin mới được xóa danh mục chi phí', 403, null, 403);
            }

            $response = DB::transaction(function () use ($expenseCategory, $admin, $request): JsonResponse {
                $expenseCategoryModel = ExpenseCategory::query()->withCount($this->counts())->lockForUpdate()->find($expenseCategory);

                if (! $expenseCategoryModel) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy danh mục chi phí', 404, null, 404);
                }

                if ((int) $expenseCategoryModel->expenses_count > 0) {
                    return ApiResponse::responseJson(false, 'Không thể xóa danh mục chi phí đã phát sinh phiếu chi. Vui lòng chuyển sang hết sử dụng.', 422, null, 422);
                }

                $oldData = $expenseCategoryModel->toArray();
                $expenseCategoryModel->delete();

                AdminActivityLogger::write($admin, 'Xóa danh mục chi phí', ExpenseCategory::class, $expenseCategoryModel->id, $oldData, null, $request);

                return ApiResponse::responseJson(true, 'Xóa danh mục chi phí thành công', 200, null, 200);
            });

            return $response;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    // Tạo cấu trúc dữ liệu lưu danh mục chi phí
    private function payload(array $validated, ?int $createdBy = null, bool $isUpdate = false): array
    {
        $payload = [];
        $fields = ['name', 'description', 'is_active'];

        if (array_key_exists('status', $validated) && ! array_key_exists('is_active', $validated)) {
            $validated['is_active'] = $validated['status'];
        }

        foreach ($fields as $field) {
            if (array_key_exists($field, $validated)) {
                $payload[$field] = $validated[$field];
            }
        }

        if (! $isUpdate) {
            $payload['created_by'] = $createdBy;
            $payload['is_active'] = $payload['is_active'] ?? ExpenseCategory::ACTIVE;
        }

        return $payload;
    }

    // Danh sách cột cần lấy của danh mục chi phí
    private function columns(): array
    {
        return ['id', 'name', 'description', 'is_active', 'created_by', 'created_at', 'updated_at'];
    }

    // Các quan hệ liên kết của danh mục chi phí
    private function relations(): array
    {
        return ['creator:id,full_name'];
    }

    // Các quan hệ cần đếm số lượng liên kết
    private function counts(): array
    {
        return ['expenses'];
    }

    // Tạo truy vấn danh sách danh mục chi phí
    private function queryExpenseCategories(array $validated): Builder
    {
        $keyword = trim($validated['keyword'] ?? '');
        $isActive = $validated['is_active'] ?? $validated['status'] ?? null;

        return ExpenseCategory::query()
            ->select($this->columns())
            ->with($this->relations())
            ->withCount($this->counts())
            ->when($keyword !== '', fn (Builder $query): Builder => $query->where(function (Builder $keywordQuery) use ($keyword): void {
                $keywordQuery->where('name', 'like', "%{$keyword}%")
                    ->orWhere('description', 'like', "%{$keyword}%");
            }))
            ->when($isActive !== null, fn (Builder $query): Builder => $query->where('is_active', (bool) $isActive))
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    // Kiểm tra quyền xem danh mục chi phí của admin
    private function canViewExpenseCategories(Admin $admin): bool
    {
        return AdminScope::isSuperAdmin($admin) || AdminScope::isBuildingManager($admin);
    }

    // Kiểm tra quyền chỉnh sửa/thao tác danh mục chi phí
    private function canMutateExpenseCategories(Admin $admin): bool
    {
        return AdminScope::isSuperAdmin($admin);
    }
}
