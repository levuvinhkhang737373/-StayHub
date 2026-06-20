<?php

namespace App\Http\Controllers\Tenant;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\ContractResource;
use App\Http\Requests\Tenant\SignContractRequest;
use App\Models\Contract;
use App\Models\ContractTenant;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
                ->orderByRaw("FIELD(status, ?, ?, ?, ?, ?) ASC", [
                    Contract::STATUS_ACTIVE,
                    Contract::STATUS_PENDING_SIGN,
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
                ->orderByRaw("FIELD(status, ?, ?, ?, ?, ?) ASC", [
                    Contract::STATUS_ACTIVE,
                    Contract::STATUS_PENDING_SIGN,
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
                'contractTenants' => fn ($query) => $query->select(['id', 'contract_id', 'tenant_id', 'join_date', 'leave_date', 'billing_start_date', 'billing_end_date', 'is_staying'])->orderBy('join_date'),
                'contractTenants.tenant:id,full_name,phone,email,identity_number',
            ]);

            return ApiResponse::responseJson(true, 'Ký hợp đồng thành công', 200, new ContractResource($contract), 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }
}
