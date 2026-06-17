<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Jobs\BulkGenerateInvoicesJob;
use App\Lib\ApiResponse;
use App\Models\Building;
use App\Models\Contract;

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
        if (!$admin) {
            return ApiResponse::responseJson(false, 'Bạn không có quyền', 403, null, 403);
        }

        // Validate access
        if ($admin->role !== \App\Models\Admin::ROLE_SUPER_ADMIN) {
            $building = Building::where('id', $validated['building_id'])->where('manager_id', $admin->id)->first();
            if (!$building) {
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
