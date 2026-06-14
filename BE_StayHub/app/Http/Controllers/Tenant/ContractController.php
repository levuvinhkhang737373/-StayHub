<?php

namespace App\Http\Controllers\Tenant;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\ContractResource;
use App\Models\Contract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

class ContractController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $tenant = $request->user('tenant');

            if (!$tenant) {
                return ApiResponse::responseJson(false, 'Khách thuê chưa đăng nhập', 401, null, 401);
            }

            // Find all contracts where this tenant is listed
            $contracts = Contract::query()
                ->whereHas('contractTenants', function (Builder $query) use ($tenant): void {
                    $query->where('tenant_id', $tenant->id);
                })
                ->with([
                    'room:id,building_id,room_number,slug,status,max_occupants,current_occupants',
                    'room.building:id,name,slug,manager_admin_id,status,address',
                    'contractTenants' => fn ($query) => $query->select(['id', 'contract_id', 'tenant_id', 'join_date', 'leave_date', 'billing_start_date', 'billing_end_date', 'is_staying'])->orderBy('join_date'),
                    'contractTenants.tenant:id,full_name,phone,email,identity_number',
                ])
                ->orderByRaw("FIELD(status, ?, ?, ?, ?) ASC", [
                    Contract::STATUS_ACTIVE,
                    Contract::STATUS_EXPIRED,
                    Contract::STATUS_LIQUIDATED,
                    Contract::STATUS_CANCELLED
                ])
                ->orderByDesc('start_date')
                ->get();

            return ApiResponse::responseJson(true, 'Danh sách hợp đồng', 200, ContractResource::collection($contracts), 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }

    public function show(Request $request): JsonResponse
    {
        try {
            $tenant = $request->user('tenant');

            if (!$tenant) {
                return ApiResponse::responseJson(false, 'Khách thuê chưa đăng nhập', 401, null, 401);
            }

            // Find the latest contract where this tenant is listed
            $contract = Contract::query()
                ->whereHas('contractTenants', function (Builder $query) use ($tenant): void {
                    $query->where('tenant_id', $tenant->id);
                })
                ->with([
                    'room:id,building_id,room_number,slug,status,max_occupants,current_occupants',
                    'room.building:id,name,slug,manager_admin_id,status,address',
                    'contractTenants' => fn ($query) => $query->select(['id', 'contract_id', 'tenant_id', 'join_date', 'leave_date', 'billing_start_date', 'billing_end_date', 'is_staying'])->orderBy('join_date'),
                    'contractTenants.tenant:id,full_name,phone,email,identity_number',
                ])
                ->orderByRaw("FIELD(status, ?, ?, ?, ?) ASC", [
                    Contract::STATUS_ACTIVE,
                    Contract::STATUS_EXPIRED,
                    Contract::STATUS_LIQUIDATED,
                    Contract::STATUS_CANCELLED
                ])
                ->orderByDesc('start_date')
                ->first();

            if (!$contract) {
                return ApiResponse::responseJson(false, 'Không tìm thấy hợp đồng của bạn', 404, null, 404);
            }

            return ApiResponse::responseJson(true, 'Thông tin hợp đồng', 200, new ContractResource($contract), 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }
}
