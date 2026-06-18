<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AdminScope;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Jobs\BulkGenerateInvoicesJob;
use App\Models\Building;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BulkGenerateInvoiceController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'building_id' => 'required|integer|exists:buildings,id',
            'billing_month' => 'required|integer|min:1|max:12',
            'billing_year' => 'required|integer|min:2020|max:2100',
        ], [
            'building_id.required' => 'Vui lòng chọn tòa nhà.',
            'building_id.exists' => 'Tòa nhà không tồn tại.',
            'billing_month.required' => 'Vui lòng chọn tháng.',
            'billing_year.required' => 'Vui lòng chọn năm.',
        ]);

        $admin = $request->user('admin');
        if (! $admin) {
            return ApiResponse::responseJson(false, 'Bạn không có quyền', 403, null, 403);
        }

        if (! AdminScope::isSuperAdmin($admin)) {
            $building = Building::query()
                ->whereKey($validated['building_id'])
                ->where('manager_admin_id', $admin->id)
                ->first();

            if (! $building) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền quản lý tòa nhà này', 403, null, 403);
            }
        }

        BulkGenerateInvoicesJob::dispatch(
            (int) $validated['building_id'],
            (int) $validated['billing_month'],
            (int) $validated['billing_year'],
            $admin->id
        )->onQueue('high');

        return ApiResponse::responseJson(true, 'Yêu cầu tạo hàng loạt hóa đơn đã được xếp hàng đợi do (thực thi ngầm).', 202, null, 202);
    }
}
