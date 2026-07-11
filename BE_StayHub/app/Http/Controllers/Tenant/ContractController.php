<?php

namespace App\Http\Controllers\Tenant;

use App\Helpers\ApiResponse;
use App\Helpers\DecimalMoney;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\SignContractRequest;
use App\Http\Resources\Tenant\ContractResource;
use App\Models\Contract;
use App\Models\ContractTenant;
use App\Models\Room;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContractController extends Controller
{
    // Danh sách hợp đồng của khách thuê
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
                    'room.building.servicePrices' => fn ($query) => $query->select(['id', 'service_id', 'building_id', 'price', 'effective_from', 'effective_to', 'status'])->orderByDesc('effective_from')->orderByDesc('id'),
                    'room.roomServices' => fn ($query) => $query->select(['id', 'room_id', 'service_id', 'is_active', 'ended_at'])->active()->orderBy('id'),
                    'room.roomServices.service:id,name,slug,charge_method,unit_name,is_required,is_active',
                    'room.roomServices.prices' => fn ($query) => $query->select(['id', 'room_service_id', 'contract_id', 'price', 'effective_from', 'effective_to'])->orderByDesc('effective_from')->orderByDesc('id'),
                    'roomServicePrices' => fn ($query) => $query->select(['id', 'contract_id', 'room_service_id', 'price', 'effective_from', 'effective_to'])->with('roomService:id,service_id,is_active,ended_at'),
                    'representativeTenant:id,full_name,phone,email',
                    'contractTenants' => fn ($query) => $query->select(['id', 'contract_id', 'tenant_id', 'join_date', 'leave_date', 'billing_start_date', 'billing_end_date', 'is_staying'])->orderBy('join_date'),
                    'contractTenants.tenant:id,full_name,phone,email,identity_number',
                    'contractVehicles.vehicle',
                ])
                ->orderByRaw($this->statusPrioritySql(), $this->statusPriorityBindings())
                ->orderByDesc('start_date')
                ->get();

            return ApiResponse::responseJson(true, 'Danh sách hợp đồng', 200, ContractResource::collection($contracts), 200);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }

    // Xem chi tiết hợp đồng
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
                    'room.building.servicePrices' => fn ($query) => $query->select(['id', 'service_id', 'building_id', 'price', 'effective_from', 'effective_to', 'status'])->orderByDesc('effective_from')->orderByDesc('id'),
                    'room.roomServices' => fn ($query) => $query->select(['id', 'room_id', 'service_id', 'is_active', 'ended_at'])->active()->orderBy('id'),
                    'room.roomServices.service:id,name,slug,charge_method,unit_name,is_required,is_active',
                    'room.roomServices.prices' => fn ($query) => $query->select(['id', 'room_service_id', 'contract_id', 'price', 'effective_from', 'effective_to'])->orderByDesc('effective_from')->orderByDesc('id'),
                    'roomServicePrices' => fn ($query) => $query->select(['id', 'contract_id', 'room_service_id', 'price', 'effective_from', 'effective_to'])->with('roomService:id,service_id,is_active,ended_at'),
                    'representativeTenant:id,full_name,phone,email',
                    'contractTenants' => fn ($query) => $query->select(['id', 'contract_id', 'tenant_id', 'join_date', 'leave_date', 'billing_start_date', 'billing_end_date', 'is_staying'])->orderBy('join_date'),
                    'contractTenants.tenant:id,full_name,phone,email,identity_number',
                    'contractVehicles.vehicle',
                ])
                ->orderByRaw($this->statusPrioritySql(), $this->statusPriorityBindings())
                ->orderByDesc('start_date')
                ->first();

            if (!$contract) {
                return ApiResponse::responseJson(false, 'Không tìm thấy hợp đồng của bạn', 404, null, 404);
            }

            return ApiResponse::responseJson(true, 'Thông tin hợp đồng', 200, new ContractResource($contract), 200);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }

    // Ký xác nhận hợp đồng điện tử
    public function sign(SignContractRequest $request, int $id): JsonResponse
    {
        try {
            $tenant = $request->user('tenant');

            if (!$tenant) {
                return ApiResponse::responseJson(false, 'Khách thuê chưa đăng nhập', 401, null, 401);
            }

            // Find the contract where this tenant is a member
            $contract = Contract::query()
                ->where('id', $id)
                ->whereHas('contractTenants', function (Builder $query) use ($tenant): void {
                    $query->where('tenant_id', $tenant->id);
                })
                ->first();

            if (!$contract) {
                return ApiResponse::responseJson(false, 'Không tìm thấy hợp đồng chờ ký phù hợp', 404, null, 404);
            }

            if ($contract->status !== Contract::STATUS_PENDING_SIGN) {
                return ApiResponse::responseJson(false, 'Hợp đồng này không ở trạng thái chờ ký hoặc không thuộc về bạn.', 400, null, 400);
            }

            $validated = $request->validated();

            DB::transaction(function () use ($tenant, $contract, $validated, $request): void {
                // 1. Update the tenant profile in the database
                $tenant->update([
                    'full_name' => $validated['full_name'],
                    'identity_number' => $validated['identity_number'],
                    'identity_type' => $validated['identity_type'],
                    'identity_date' => $validated['identity_date'],
                    'identity_place' => $validated['identity_place'],
                    'permanent_address' => $validated['permanent_address'],
                ]);

                // 2. Upload and store the signature file directly to public/upload/signatures/
                $file = $request->file('signature_file');
                $filename = "contract_{$contract->contract_code}_sign.png"; // Save as png
                $destinationFolder = public_path('upload/signatures');
                if (!is_dir($destinationFolder)) {
                    mkdir($destinationFolder, 0755, true);
                }
                $file->move($destinationFolder, $filename);
                $path = "upload/signatures/{$filename}";

                // 3. Update the contract status, signature path, and signed_at timestamp
                $contract->update([
                    'status' => Contract::STATUS_ACTIVE,
                    'representative_tenant_id' => $contract->representative_tenant_id ?: $tenant->id,
                    'tenant_signed_at' => now(),
                    'tenant_signature_url' => $path,
                ]);

                // 4. Refresh Room Occupants
                $roomId = $contract->room_id;
                $occupants = ContractTenant::query()
                    ->where('is_staying', true)
                    ->whereNull('leave_date')
                    ->whereHas('contract', fn (Builder $query): Builder => $query->where('room_id', $roomId)->where('status', Contract::STATUS_ACTIVE))
                    ->distinct('tenant_id')
                    ->count('tenant_id');

                Room::query()->whereKey($roomId)->update(['current_occupants' => $occupants]);

            });

            // 5. Create and broadcast database notification for building admins
            try {
                $contract->loadMissing(['room.building']);
                $room = $contract->room;
                $building = $room?->building;

                $adminNotif = \App\Models\Notification::create([
                    'title' => 'Hợp đồng đã được ký',
                    'content' => "Khách thuê {$tenant->full_name} đã ký hợp đồng {$contract->contract_code} cho phòng " . ($room?->room_number ?? 'chưa rõ') . " tại tòa nhà " . ($building?->name ?? 'chưa rõ') . ".",
                    'notification_type' => \App\Models\Notification::NOTIFICATION_TYPE_SYSTEM,
                    'target_type' => \App\Models\Notification::TARGET_TYPE_ADMIN,
                    'building_id' => $room?->building_id,
                    'room_id' => $contract->room_id,
                    'tenant_id' => $tenant->id,
                    'status' => \App\Models\Notification::STATUS_SENT,
                    'published_at' => now(),
                ]);

                // Broadcast real-time notification to building manager
                broadcast(new \App\Events\NotificationSent($adminNotif));
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Tenant Contract sign notification error: ' . $e->getMessage());
            }

            // Reload relationships and return
            $contract->load([
                'room:id,building_id,room_number,slug,status,max_occupants,current_occupants',
                'room.building:id,name,slug,manager_admin_id,status,address',
                'room.building.servicePrices' => fn ($query) => $query->select(['id', 'service_id', 'building_id', 'price', 'effective_from', 'effective_to', 'status'])->orderByDesc('effective_from')->orderByDesc('id'),
                'room.roomServices' => fn ($query) => $query->select(['id', 'room_id', 'service_id', 'is_active', 'ended_at'])->active()->orderBy('id'),
                'room.roomServices.service:id,name,slug,charge_method,unit_name,is_required,is_active',
                'room.roomServices.prices' => fn ($query) => $query->select(['id', 'room_service_id', 'contract_id', 'price', 'effective_from', 'effective_to'])->orderByDesc('effective_from')->orderByDesc('id'),
                'roomServicePrices' => fn ($query) => $query->select(['id', 'contract_id', 'room_service_id', 'price', 'effective_from', 'effective_to'])->with('roomService:id,service_id,is_active,ended_at'),
                'representativeTenant:id,full_name,phone,email',
                'contractTenants' => fn ($query) => $query->select(['id', 'contract_id', 'tenant_id', 'join_date', 'leave_date', 'billing_start_date', 'billing_end_date', 'is_staying'])->orderBy('join_date'),
                'contractTenants.tenant:id,full_name,phone,email,identity_number',
                'contractVehicles.vehicle',
            ]);

            return ApiResponse::responseJson(true, 'Ký hợp đồng thành công', 200, new ContractResource($contract), 200);
        } catch (ValidationException $e) {
            $firstError = collect($e->errors())->flatten()->first() ?? $e->getMessage();
            return ApiResponse::responseJson(false, $firstError, 422, $e->errors(), 422);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }

    // Sắp xếp độ ưu tiên trạng thái hợp đồng bằng SQL
    private function statusPrioritySql(): string
    {
        return 'CASE status WHEN ? THEN 0 WHEN ? THEN 1 WHEN ? THEN 2 WHEN ? THEN 3 WHEN ? THEN 4 ELSE 5 END ASC';
    }

    // Tham số ràng buộc cho sắp xếp trạng thái hợp đồng
    private function statusPriorityBindings(): array
    {
        return [
            Contract::STATUS_ACTIVE,
            Contract::STATUS_PENDING_SIGN,
            Contract::STATUS_EXPIRED,
            Contract::STATUS_LIQUIDATED,
            Contract::STATUS_CANCELLED,
        ];
    }


}
