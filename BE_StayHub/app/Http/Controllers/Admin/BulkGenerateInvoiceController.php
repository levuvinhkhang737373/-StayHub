<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AdminActivityLogger;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Invoice\BulkGenerateRequest;
use App\Jobs\BulkGenerateInvoicesJob;
use App\Models\Building;
use Illuminate\Http\JsonResponse;

class BulkGenerateInvoiceController extends Controller
{
    // Tạo hóa đơn hàng loạt cho tòa nhà
    public function __invoke(BulkGenerateRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $admin = $request->user('admin');

        BulkGenerateInvoicesJob::dispatch(
            (int) $validated['building_id'],
            (int) $validated['billing_month'],
            (int) $validated['billing_year'],
            $admin->id
        )->onQueue('high');

        AdminActivityLogger::write($admin, 'Xếp hàng tạo hóa đơn hàng loạt', Building::class, (int) $validated['building_id'], null, [
            'building_id' => (int) $validated['building_id'],
            'billing_month' => (int) $validated['billing_month'],
            'billing_year' => (int) $validated['billing_year'],
        ], $request);

        return ApiResponse::responseJson(true, 'Yêu cầu tạo hàng loạt hóa đơn đã được xếp hàng đợi do (thực thi ngầm).', 202, null, 202);
    }
}
