<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AdminActivityLogger;
use App\Helpers\AdminScope;
use App\Helpers\ApiResponse;
use App\Helpers\DecimalMoney;
use App\Helpers\ImageHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Expense\CancelRequest;
use App\Http\Requests\Admin\Expense\IndexRequest;
use App\Http\Requests\Admin\Expense\RegisterRequest;
use App\Http\Requests\Admin\Expense\UpdateRequest;
use App\Http\Resources\Admin\ExpenseDetailResource;
use App\Http\Resources\Admin\ExpenseResource;
use App\Models\Admin;
use App\Models\Expense;
use App\Models\Room;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExpenseController extends Controller
{
    private const MAX_RECEIPT_IMAGES = 10;

    public function index(IndexRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');
            $expenses = $this->queryExpenses($validated, $admin)->paginate($validated['per_page'] ?? 20);

            return ApiResponse::responseJson(true, 'Danh sách phiếu chi', 200, [
                'data' => ExpenseResource::collection($expenses->items())->resolve(),
                'pagination' => [
                    'current_page' => $expenses->currentPage(),
                    'per_page' => $expenses->perPage(),
                    'total' => $expenses->total(),
                    'last_page' => $expenses->lastPage(),
                ],
            ], 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function store(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            if (! AdminScope::ensureBuildingAccess($admin, (int) $validated['building_id'])) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền tạo phiếu chi cho tòa nhà này', 403, null, 403);
            }

            if (! $this->roomBelongsToBuilding($validated['room_id'] ?? null, (int) $validated['building_id'])) {
                return ApiResponse::responseJson(false, 'Phòng không thuộc tòa nhà đã chọn', 422, null, 422);
            }

            $uploadedPaths = $this->uploadReceiptImages($request);

            $response = DB::transaction(function () use ($validated, $uploadedPaths, $admin, $request): JsonResponse {
                $expense = Expense::query()->create(array_merge($this->payload($validated), [
                    'expense_code' => $this->makeExpenseCode(),
                    'receipt_images' => $uploadedPaths,
                    'status' => Expense::STATUS_RECORDED,
                    'created_by' => $admin->id,
                ]));

                AdminActivityLogger::write($admin, 'create_expense', Expense::class, $expense->id, null, $expense->toArray(), $request);

                $expense->load($this->detailRelations());

                return ApiResponse::responseJson(true, 'Tạo phiếu chi thành công', 201, new ExpenseDetailResource($expense), 201);
            });

            return $response;
        } catch (\Exception $e) {
            collect($uploadedPaths ?? [])->each(fn (string $path): bool => ImageHelper::delete($path));

            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function show(Request $request, int $expense): JsonResponse
    {
        try {
            $admin = $request->user('admin');

            if (! $this->canAccessExpenses($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xem phiếu chi', 403, null, 403);
            }

            $expenseModel = $this->accessibleExpenseQuery($admin)
                ->select($this->columns())
                ->with($this->detailRelations())
                ->find($expense);

            if (! $expenseModel) {
                return ApiResponse::responseJson(false, 'Không tìm thấy phiếu chi', 404, null, 404);
            }

            return ApiResponse::responseJson(true, 'Chi tiết phiếu chi', 200, new ExpenseDetailResource($expenseModel), 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function update(UpdateRequest $request, int $expense): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            if (! AdminScope::ensureBuildingAccess($admin, (int) $validated['building_id'])) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền cập nhật phiếu chi cho tòa nhà này', 403, null, 403);
            }

            if (! $this->roomBelongsToBuilding($validated['room_id'] ?? null, (int) $validated['building_id'])) {
                return ApiResponse::responseJson(false, 'Phòng không thuộc tòa nhà đã chọn', 422, null, 422);
            }

            $uploadedPaths = $this->uploadReceiptImages($request);

            $response = DB::transaction(function () use ($validated, $expense, $uploadedPaths, $admin, $request): JsonResponse {
                $expenseModel = $this->accessibleExpenseQuery($admin)->lockForUpdate()->find($expense);

                if (! $expenseModel) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy phiếu chi', 404, null, 404);
                }

                if ((int) $expenseModel->status === Expense::STATUS_CANCELLED) {
                    return ApiResponse::responseJson(false, 'Không thể cập nhật phiếu chi đã hủy', 422, null, 422);
                }

                $oldData = $expenseModel->toArray();
                $receiptImages = $this->mergeReceiptImages($expenseModel->receipt_images ?? [], $uploadedPaths, $validated['deleted_receipt_images'] ?? []);
                $deletedImages = array_values(array_intersect($expenseModel->receipt_images ?? [], $validated['deleted_receipt_images'] ?? []));

                if (count($receiptImages) > self::MAX_RECEIPT_IMAGES) {
                    return ApiResponse::responseJson(false, 'Tối đa 10 ảnh chứng từ cho một phiếu chi', 422, null, 422);
                }

                $expenseModel->fill(array_merge($this->payload($validated), [
                    'receipt_images' => $receiptImages,
                ]))->save();

                collect($deletedImages)->each(fn (string $path): bool => ImageHelper::delete($path));

                AdminActivityLogger::write($admin, 'update_expense', Expense::class, $expenseModel->id, $oldData, $expenseModel->fresh()->toArray(), $request);

                $expenseModel->load($this->detailRelations());

                return ApiResponse::responseJson(true, 'Cập nhật phiếu chi thành công', 200, new ExpenseDetailResource($expenseModel), 200);
            });

            if ($response->getStatusCode() >= 400) {
                collect($uploadedPaths)->each(fn (string $path): bool => ImageHelper::delete($path));
            }

            return $response;
        } catch (\Exception $e) {
            collect($uploadedPaths ?? [])->each(fn (string $path): bool => ImageHelper::delete($path));

            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function cancel(CancelRequest $request, int $expense): JsonResponse
    {
        try {
            $admin = $request->user('admin');
            $response = DB::transaction(function () use ($expense, $admin, $request): JsonResponse {
                $expenseModel = $this->accessibleExpenseQuery($admin)->lockForUpdate()->find($expense);

                if (! $expenseModel) {
                    return ApiResponse::responseJson(false, 'Không tìm thấy phiếu chi', 404, null, 404);
                }

                if ((int) $expenseModel->status === Expense::STATUS_CANCELLED) {
                    return ApiResponse::responseJson(false, 'Phiếu chi đã được hủy trước đó', 422, null, 422);
                }

                $oldData = $expenseModel->toArray();
                $expenseModel->forceFill(['status' => Expense::STATUS_CANCELLED])->save();

                AdminActivityLogger::write($admin, 'cancel_expense', Expense::class, $expenseModel->id, $oldData, $expenseModel->fresh()->toArray(), $request);

                $expenseModel->load($this->detailRelations());

                return ApiResponse::responseJson(true, 'Hủy phiếu chi thành công', 200, new ExpenseDetailResource($expenseModel), 200);
            });

            return $response;
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function destroy(CancelRequest $request, int $expense): JsonResponse
    {
        return $this->cancel($request, $expense);
    }

    private function queryExpenses(array $validated, Admin $admin): Builder
    {
        $keyword = trim($validated['keyword'] ?? '');

        return $this->accessibleExpenseQuery($admin)
            ->select($this->columns())
            ->with($this->listRelations())
            ->when($keyword !== '', fn (Builder $query): Builder => $query->where(function (Builder $keywordQuery) use ($keyword): void {
                $keywordQuery->where('expense_code', 'like', "%{$keyword}%")
                    ->orWhere('title', 'like', "%{$keyword}%")
                    ->orWhere('note', 'like', "%{$keyword}%")
                    ->orWhereHas('building', fn (Builder $buildingQuery): Builder => $buildingQuery->where('name', 'like', "%{$keyword}%"))
                    ->orWhereHas('room', fn (Builder $roomQuery): Builder => $roomQuery->where('room_number', 'like', "%{$keyword}%"))
                    ->orWhereHas('category', fn (Builder $categoryQuery): Builder => $categoryQuery->where('name', 'like', "%{$keyword}%"));
            }))
            ->when(isset($validated['building_id']), fn (Builder $query): Builder => $query->where('building_id', (int) $validated['building_id']))
            ->when(isset($validated['room_id']), fn (Builder $query): Builder => $query->where('room_id', (int) $validated['room_id']))
            ->when(isset($validated['expense_category_id']), fn (Builder $query): Builder => $query->where('expense_category_id', (int) $validated['expense_category_id']))
            ->when(isset($validated['payment_method']), fn (Builder $query): Builder => $query->where('payment_method', (int) $validated['payment_method']))
            ->when(isset($validated['status']), fn (Builder $query): Builder => $query->where('status', (int) $validated['status']))
            ->when(isset($validated['expense_date_from']), fn (Builder $query): Builder => $query->whereDate('expense_date', '>=', $validated['expense_date_from']))
            ->when(isset($validated['expense_date_to']), fn (Builder $query): Builder => $query->whereDate('expense_date', '<=', $validated['expense_date_to']))
            ->orderByDesc('expense_date')
            ->orderByDesc('id');
    }

    private function accessibleExpenseQuery(Admin $admin): Builder
    {
        return AdminScope::applyBuildingScope(Expense::query(), $admin, 'building_id');
    }

    private function canAccessExpenses(?Admin $admin): bool
    {
        return $admin && (AdminScope::isSuperAdmin($admin) || AdminScope::isBuildingManager($admin));
    }

    private function payload(array $validated): array
    {
        $payload = [];

        foreach (['building_id', 'room_id', 'expense_category_id', 'title', 'expense_date', 'payment_method', 'note'] as $field) {
            if (array_key_exists($field, $validated)) {
                $payload[$field] = $validated[$field];
            }
        }

        if (array_key_exists('amount', $validated)) {
            $payload['amount'] = DecimalMoney::normalize($validated['amount']);
        }

        $payload['payment_method'] = $payload['payment_method'] ?? Expense::PAYMENT_METHOD_CASH;

        return $payload;
    }

    private function uploadReceiptImages(Request $request): array
    {
        return collect($request->file('receipt_images', []))
            ->map(fn ($image): string => ImageHelper::create($image, 'expenses'))
            ->values()
            ->all();
    }

    private function mergeReceiptImages(array $currentImages, array $newImages, array $deletedImages): array
    {
        $deleteSet = array_flip($deletedImages);
        $remainingImages = collect($currentImages)
            ->filter(fn (string $path): bool => ! isset($deleteSet[$path]))
            ->values()
            ->all();

        return array_values(array_unique(array_merge($remainingImages, $newImages)));
    }

    private function roomBelongsToBuilding(mixed $roomId, int $buildingId): bool
    {
        if (blank($roomId)) {
            return true;
        }

        return Room::query()
            ->whereKey((int) $roomId)
            ->where('building_id', $buildingId)
            ->exists();
    }

    private function makeExpenseCode(): string
    {
        $prefix = 'EXP-'.now()->format('Y-m').'-';
        $next = Expense::query()
            ->where('expense_code', 'like', $prefix.'%')
            ->lockForUpdate()
            ->count() + 1;

        do {
            $code = $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            $next++;
        } while (Expense::query()->where('expense_code', $code)->exists());

        return $code;
    }

    private function columns(): array
    {
        return ['id', 'expense_code', 'building_id', 'room_id', 'expense_category_id', 'title', 'amount', 'expense_date', 'receipt_images', 'payment_method', 'note', 'status', 'created_by', 'created_at', 'updated_at'];
    }

    private function listRelations(): array
    {
        return [
            'building:id,name,manager_admin_id',
            'room:id,building_id,room_number',
            'category:id,name,is_active',
            'creator:id,full_name',
        ];
    }

    private function detailRelations(): array
    {
        return [
            'building:id,name,manager_admin_id',
            'room:id,building_id,room_number,floor,status',
            'category:id,name,is_active',
            'creator:id,full_name',
        ];
    }
}
