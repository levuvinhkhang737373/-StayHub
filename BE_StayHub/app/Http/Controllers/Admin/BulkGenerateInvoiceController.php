<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Invoice\BulkGenerateRequest;
use App\Jobs\BulkGenerateInvoicesJob;
use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;

class BulkGenerateInvoiceController extends Controller
{
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

    return ApiResponse::responseJson(true, 'Yêu cầu tạo hàng loạt hóa đơn đã được xếp hàng đợi do (thực thi ngầm).', 202, null, 202);
    }
}

