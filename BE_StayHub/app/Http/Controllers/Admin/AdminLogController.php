<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AdminActivityLogger;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminLog\IndexRequest;
use App\Http\Resources\Admin\AdminLogResource;
use App\Models\AdminLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminLogController extends Controller
{
    // Danh sách nhật ký hoạt động của admin
    public function index(IndexRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $logs = $this->queryLogs($validated)->paginate($validated['per_page'] ?? 20);

            return ApiResponse::responseJson(true, 'Danh sách nhật ký thao tác admin', 200, $this->paginatedResource($logs), 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    // Xem chi tiết nhật ký hoạt động
    public function show(Request $request, AdminLog $adminLog): JsonResponse
    {
        try {
            $adminLog->load($this->adminLogRelations());

            return ApiResponse::responseJson(true, 'Chi tiết nhật ký thao tác admin', 200, new AdminLogResource($adminLog), 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    // Tạo truy vấn nhật ký hoạt động
    private function queryLogs(array $validated): Builder
    {
        $keyword = trim($validated['keyword'] ?? '');

        return AdminLog::query()
            ->with($this->adminLogRelations())
            ->when($keyword !== '', fn (Builder $query): Builder => $this->applyKeywordFilter($query, $keyword))
            ->when(isset($validated['admin_id']), fn (Builder $query): Builder => $query->where('admin_id', (int) $validated['admin_id']))
            ->when(isset($validated['action']), fn (Builder $query): Builder => $query->where('action', AdminActivityLogger::normalizeAction($validated['action'])))
            ->when(isset($validated['entity_type']), fn (Builder $query): Builder => $query->where('entity_type', $validated['entity_type']))
            ->when(isset($validated['entity_id']), fn (Builder $query): Builder => $query->where('entity_id', (int) $validated['entity_id']))
            ->when(isset($validated['date_from']), fn (Builder $query): Builder => $query->whereDate('created_at', '>=', $validated['date_from']))
            ->when(isset($validated['date_to']), fn (Builder $query): Builder => $query->whereDate('created_at', '<=', $validated['date_to']))
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    // Tìm kiếm nhật ký theo từ khóa
    private function applyKeywordFilter(Builder $query, string $keyword): Builder
    {
        return $query->where(function (Builder $keywordQuery) use ($keyword): void {
            $keywordQuery->where('action', 'like', "%{$keyword}%")
                ->orWhere('entity_type', 'like', "%{$keyword}%")
                ->orWhere('old_data', 'like', "%{$keyword}%")
                ->orWhere('new_data', 'like', "%{$keyword}%")
                ->orWhere('ip_address', 'like', "%{$keyword}%")
                ->orWhere('user_agent', 'like', "%{$keyword}%")
                ->orWhereHas('admin', function (Builder $adminQuery) use ($keyword): void {
                    $adminQuery->where('username', 'like', "%{$keyword}%")
                        ->orWhere('full_name', 'like', "%{$keyword}%")
                        ->orWhere('email', 'like', "%{$keyword}%");
                });

            if (is_numeric($keyword)) {
                $keywordQuery->orWhere('entity_id', (int) $keyword);
            }
        });
    }

    // Các quan hệ liên kết của nhật ký hoạt động
    private function adminLogRelations(): array
    {
        return [
            'admin:id,username,full_name,email,role,status',
            'admin.managedBuildings:id,manager_admin_id,name',
        ];
    }

    // Định dạng dữ liệu nhật ký hoạt động phân trang
    private function paginatedResource(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => AdminLogResource::collection($paginator->items())->resolve(),
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
}
