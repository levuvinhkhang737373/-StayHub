<?php

namespace App\Http\Controllers\Tenant;

use App\Events\InvoiceIssued;
use App\Events\NotificationSent;
use App\Helpers\ApiResponse;
use App\Helpers\DecimalMoney;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\SignContractRequest;
use App\Http\Resources\Tenant\ContractResource;
use App\Models\Admin;
use App\Models\Contract;
use App\Models\ContractTenant;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Notification;
use App\Models\Room;
use App\Models\RoomMovement;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
                    'room.services',
                    'representativeTenant:id,full_name,phone,email',
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
                    'room.services',
                    'representativeTenant:id,full_name,phone,email',
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

                $this->issueTransferContractInvoiceIfNeeded($contract, $tenant);
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
                'representativeTenant:id,full_name,phone,email',
                'contractTenants' => fn ($query) => $query->select(['id', 'contract_id', 'tenant_id', 'join_date', 'leave_date', 'billing_start_date', 'billing_end_date', 'is_staying'])->orderBy('join_date'),
                'contractTenants.tenant:id,full_name,phone,email,identity_number',
            ]);

            return ApiResponse::responseJson(true, 'Ký hợp đồng thành công', 200, new ContractResource($contract), 200);
        } catch (ValidationException $e) {
            $firstError = collect($e->errors())->flatten()->first() ?? $e->getMessage();
            return ApiResponse::responseJson(false, $firstError, 422, $e->errors(), 422);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }

    private function issueTransferContractInvoiceIfNeeded(Contract $contract, Tenant $tenant): ?Invoice
    {
        if (! $contract->parent_contract_id || ! $contract->start_date) {
            return null;
        }

        $isTransferContract = RoomMovement::query()
            ->where('contract_id', $contract->id)
            ->where('tenant_id', $tenant->id)
            ->where('movement_type', RoomMovement::MOVEMENT_TYPE_TRANSFER)
            ->exists();

        if (! $isTransferContract) {
            return null;
        }

        $admin = Admin::query()->find($contract->created_by);
        if (! $admin) {
            throw ValidationException::withMessages([
                'contract' => 'Không tìm thấy admin tạo hợp đồng chuyển phòng để phát hành hóa đơn.',
            ]);
        }

        $periodStart = $contract->start_date->copy()->startOfDay();
        $periodEnd = $periodStart->copy()->endOfMonth()->startOfDay();
        $contractEndDate = $contract->actual_end_date ?: $contract->end_date;

        if ($contractEndDate && $contractEndDate->copy()->startOfDay()->lessThan($periodEnd)) {
            $periodEnd = $contractEndDate->copy()->startOfDay();
        }

        if ($periodStart->greaterThan($periodEnd)) {
            return null;
        }

        $existingInvoice = Invoice::query()
            ->where('contract_id', $contract->id)
            ->where('billing_year', $periodStart->year)
            ->where('billing_month', $periodStart->month)
            ->lockForUpdate()
            ->first();

        if ($existingInvoice) {
            return $existingInvoice;
        }

        $actualDays = $periodStart->diffInDays($periodEnd) + 1;
        $totalDays = $periodStart->daysInMonth;
        $roomAmount = DecimalMoney::prorateByDays($contract->room_price, $actualDays, $totalDays);
        $status = DecimalMoney::isPositive($roomAmount) ? Invoice::STATUS_UNPAID : Invoice::STATUS_PAID;

        $invoice = Invoice::query()->create([
            'invoice_code' => $this->makeTransferInvoiceCode($periodStart),
            'contract_id' => $contract->id,
            'room_id' => $contract->room_id,
            'billing_month' => $periodStart->month,
            'billing_year' => $periodStart->year,
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'previous_debt_amount' => '0.00',
            'total_amount' => $roomAmount,
            'paid_amount' => '0.00',
            'remaining_amount' => $roomAmount,
            'due_date' => now()->addDays(7)->toDateString(),
            'status' => $status,
            'issued_at' => now(),
            'created_by' => $admin->id,
        ]);

        $invoice->items()->create([
            'service_id' => null,
            'meter_reading_id' => null,
            'item_type' => InvoiceItem::ITEM_TYPE_ROOM,
            'description' => 'Tiền phòng mới sau chuyển phòng từ '.$periodStart->format('d/m/Y').' đến '.$periodEnd->format('d/m/Y'),
            'quantity' => (string) $actualDays,
            'unit_price' => DecimalMoney::normalize($contract->room_price),
            'amount' => $roomAmount,
        ]);

        $notification = $this->createTransferInvoiceNotification($invoice, $admin, $tenant);

        DB::afterCommit(function () use ($invoice, $tenant, $notification): void {
            event(new InvoiceIssued($invoice->fresh(['room.building', 'contract.contractTenants.tenant']), [$tenant->id]));
            event(new NotificationSent($notification));
        });

        return $invoice->fresh(['room.building', 'contract.contractTenants.tenant']);
    }

    private function createTransferInvoiceNotification(Invoice $invoice, Admin $admin, Tenant $tenant): Notification
    {
        $invoice->loadMissing('room.building');

        return Notification::query()->create([
            'title' => 'Hóa đơn phòng mới đã được phát hành',
            'content' => 'Hóa đơn phòng mới sau khi ký hợp đồng chuyển phòng đã được phát hành. Vui lòng mở mục Hóa đơn để xem chi tiết và chuyển khoản.',
            'notification_type' => Notification::NOTIFICATION_TYPE_INVOICE,
            'target_type' => Notification::TARGET_TYPE_TENANT,
            'building_id' => $invoice->room?->building_id,
            'room_id' => $invoice->room_id,
            'tenant_id' => $tenant->id,
            'published_at' => now(),
            'status' => Notification::STATUS_SENT,
            'created_by' => $admin->id,
        ]);
    }

    private function makeTransferInvoiceCode(\Carbon\Carbon $periodStart): string
    {
        $prefix = 'TRF-NEW-'.$periodStart->format('Y-m').'-';
        $next = Invoice::query()
            ->where('invoice_code', 'like', $prefix.'%')
            ->lockForUpdate()
            ->count() + 1;

        do {
            $code = $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            $next++;
        } while (Invoice::query()->where('invoice_code', $code)->exists());

        return $code;
    }

    public function negotiate(Request $request, int $id): JsonResponse
    {
        try {
            $tenant = $request->user('tenant');
            if (!$tenant) {
                return ApiResponse::responseJson(false, 'Khách thuê chưa đăng nhập', 401, null, 401);
            }

            $contract = Contract::query()
                ->where('id', $id)
                ->whereHas('contractTenants', function (Builder $query) use ($tenant): void {
                    $query->where('tenant_id', $tenant->id);
                })
                ->with(['room'])
                ->first();

            if (!$contract) {
                return ApiResponse::responseJson(false, 'Không tìm thấy hợp đồng', 404, null, 404);
            }

            if ($contract->status !== Contract::STATUS_PENDING_SIGN) {
                return ApiResponse::responseJson(false, 'Hợp đồng này không ở trạng thái chờ ký.', 400, null, 400);
            }

            if ($contract->negotiation_status === Contract::NEGOTIATION_STATUS_APPROVED) {
                return ApiResponse::responseJson(false, 'Thương lượng đã được đồng ý và áp dụng giá, không thể sửa đổi.', 400, null, 400);
            }

            $validated = $request->validate([
                'room_price' => 'required|numeric|min:0',
                'services' => 'nullable|array',
                'services.*.service_id' => 'required|exists:services,id',
                'services.*.price' => 'required|numeric|min:0',
            ]);

            // Validate that electricity and water are not negotiated
            $servicesInput = $validated['services'] ?? [];
            if (!empty($servicesInput)) {
                $serviceIds = collect($servicesInput)->pluck('service_id')->toArray();
                $meteredServices = \App\Models\Service::query()
                    ->whereIn('id', $serviceIds)
                    ->where(function ($query) {
                        $query->where('charge_method', \App\Models\Service::CHARGE_METHOD_BY_METER)
                              ->orWhereIn('slug', ['electric', 'water', 'electricity', 'dien-sinh-hoat', 'nuoc-sinh-hoat']);
                    })
                    ->get()
                    ->keyBy('id');

                foreach ($servicesInput as $serviceInput) {
                    $sId = $serviceInput['service_id'];
                    if ($meteredServices->has($sId)) {
                        // Check if the proposed price matches the current room service price or building price
                        $currentRoomService = \App\Models\RoomService::where('room_id', $contract->room_id)
                            ->where('service_id', $sId)
                            ->first();
                        
                        $currentPrice = $currentRoomService ? $currentRoomService->price : null;
                        if ($currentPrice === null) {
                            $buildingPrice = \App\Models\ServicePrice::where('building_id', $contract->room->building_id)
                                ->where('service_id', $sId)
                                ->where('status', \App\Models\ServicePrice::STATUS_ACTIVE)
                                ->first();
                            $currentPrice = $buildingPrice ? $buildingPrice->price : '0.00';
                        }

                        if (DecimalMoney::normalize((string)$serviceInput['price']) !== DecimalMoney::normalize((string)$currentPrice)) {
                            return ApiResponse::responseJson(false, "Không thể thương lượng giá của dịch vụ điện/nước ({$meteredServices->get($sId)->name}).", 422, null, 422);
                        }
                    }
                }
            }

            // Update contract negotiation info
            $contract->update([
                'negotiation_status' => Contract::NEGOTIATION_STATUS_PENDING,
                'proposed_room_price' => $validated['room_price'],
                'proposed_services' => $servicesInput,
            ]);

            // Notify Building Manager
            try {
                $contract->loadMissing(['room.building']);
                $room = $contract->room;
                $building = $room?->building;

                $adminNotif = \App\Models\Notification::create([
                    'title' => 'Yêu cầu thương lượng giá hợp đồng',
                    'content' => "Khách thuê {$tenant->full_name} đã gửi yêu cầu thương lượng giá hợp đồng {$contract->contract_code} cho phòng " . ($room?->room_number ?? 'chưa rõ') . " tại tòa nhà " . ($building?->name ?? 'chưa rõ') . ".",
                    'notification_type' => \App\Models\Notification::NOTIFICATION_TYPE_SYSTEM,
                    'target_type' => \App\Models\Notification::TARGET_TYPE_ADMIN,
                    'building_id' => $room?->building_id,
                    'room_id' => $contract->room_id,
                    'tenant_id' => $tenant->id,
                    'status' => \App\Models\Notification::STATUS_SENT,
                    'published_at' => now(),
                ]);

                broadcast(new \App\Events\NotificationSent($adminNotif));
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Tenant Contract negotiate notification error: ' . $e->getMessage());
            }

            $contract->load([
                'room:id,building_id,room_number,slug,status,max_occupants,current_occupants',
                'room.building:id,name,slug,manager_admin_id,status,address',
                'room.services',
                'representativeTenant:id,full_name,phone,email',
                'contractTenants' => fn ($query) => $query->select(['id', 'contract_id', 'tenant_id', 'join_date', 'leave_date', 'billing_start_date', 'billing_end_date', 'is_staying'])->orderBy('join_date'),
                'contractTenants.tenant:id,full_name,phone,email,identity_number',
            ]);

            return ApiResponse::responseJson(true, 'Gửi yêu cầu thương lượng thành công', 200, new ContractResource($contract), 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $firstError = collect($e->errors())->flatten()->first() ?? $e->getMessage();
            return ApiResponse::responseJson(false, $firstError, 422, $e->errors(), 422);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: ' . $e->getMessage(), 500, null, 500);
        }
    }
}
