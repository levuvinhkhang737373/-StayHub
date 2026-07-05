<?php

namespace App\Http\Controllers\Admin;

use App\Console\Commands\ExecuteScheduledRoomTransfers;
use App\Events\NotificationSent;
use App\Helpers\AdminActivityLogger;
use App\Helpers\AdminScope;
use App\Helpers\ApiResponse;
use App\Helpers\DecimalMoney;
use App\Helpers\ImageHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Room\RoomRequest;
use App\Http\Requests\Admin\Room\TranferSingleTenantRequest;
use App\Http\Resources\Admin\RoomTransferResultResource;
use App\Models\Admin;
use App\Models\AssetTemplate;
use App\Models\Building;
use App\Models\Contract;
use App\Models\ContractTenant;
use App\Models\Invoice;
use App\Models\MeterDevice;
use App\Models\Notification;
use App\Models\Room;
use App\Models\RoomAsset;
use App\Models\RoomMovement;
use App\Models\RoomType;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class RoomController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $admin = $request->user();
        try {
            $query = Room::with("building")->with("roomType")->with('images')->with('assets');
            //Super admin xem toàn bộ, quản lý tòa nhà chỉ xem tòa nhà mình quản lý, role khác không thấy dữ liệu.
            $query = AdminScope::applyBuildingScope($query, $admin, 'building_id');
            $rooms = $query->orderBy('id', 'desc')->get();
            return ApiResponse::responseJson(true, "danh sách phòng", 200, $rooms, 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Lỗi server: ' . $e->getMessage(), 500, null, 500);
        }
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(RoomRequest $request)
    {
        $admin = $request->user();

        if (!AdminScope::isSuperAdmin($admin)) {
            return ApiResponse::responseJson(false, 'Bạn không có quyền truy cập vào tòa nhà này', 403, null, 403);
        }
        $loai_phong_hoat_dong = RoomType::where('id', $request->room_type_id)->where('status', RoomType::STATUS_ACTIVE)->first();
        if (!$loai_phong_hoat_dong) {
            return ApiResponse::responseJson(false, 'Loại phòng đã ngừng hoạt động', 400, null, 400);
        }
        $uploadedImagePaths = [];
        DB::beginTransaction();
        try {
            $validatedData = $request->validated();

            $room = Room::create([
                'building_id'   => $validatedData['building_id'],
                'room_type_id'  => $validatedData['room_type_id'],
                'room_number'   => $validatedData['room_number'],
                'floor'         => $validatedData['floor'],
                'area_m2'       => $validatedData['area_m2'],
                'base_price'    => $validatedData['base_price'],
                'max_occupants' => $validatedData['max_occupants'],
                'description'   => $validatedData['description'] ?? null,
                'created_by'    => $admin->id,
            ]);

            // Xử lý ảnh
            if ($request->hasFile('images')) {
                $isPrimary = true;
                $sortOrder = 1;
                foreach ($request->file('images') as $image) {
                    $path = ImageHelper::create($image, 'rooms');
                    $uploadedImagePaths[] = $path;
                    $room->images()->create([
                        'image_path'  => $path,
                        'is_primary'  => $isPrimary ? 1 : 0,
                        'sort_order'  => $sortOrder,
                        'status'      => 1,
                        'uploaded_by' => $admin->id,
                    ]);
                    $isPrimary = false;
                    $sortOrder++;
                }
            }

            if ($request->has('meters') && is_array($request->meters)) {
                foreach ($request->meters as $meter) {
                    MeterDevice::create([
                        'room_id' => $room->id,
                        'service_id' => $meter['service_id'],
                        'meter_code' => $meter['service_id'] == 1 ?   $room->room_number . "-DHD-{$room->id}" :   $room->room_number . "-DHN-{$room->id}",
                        'meter_type' => $meter['meter_type'],
                        'initial_reading' => $meter['initial_reading'],
                        'status' => MeterDevice::STATUS_ACTIVE,
                        'installed_at' => now(),
                    ]);
                }
            }

            if ($request->has('assets') && is_array($request->assets)) {
                foreach ($request->assets as $assetInput) {
                    RoomAsset::create([
                        'room_id'           => $room->id,
                        'asset_template_id' => $assetInput['template_id'],
                        'quantity'          => $assetInput['quantity'],
                        'price'             => $assetInput['price'] ?? null,
                        'note'              => $assetInput['note'] ?? 'Được cấu hình khi tạo phòng.',
                    ]);
                }
            }

            // Đồng bộ dịch vụ hoạt động của tòa nhà sang room_services cho phòng mới tạo
            $activeBuildingServices = \App\Models\ServicePrice::query()
                ->where('building_id', $room->building_id)
                ->where('status', \App\Models\ServicePrice::STATUS_ACTIVE)
                ->get();
            foreach ($activeBuildingServices as $buildingPrice) {
                \App\Models\RoomService::create([
                    'room_id' => $room->id,
                    'service_id' => $buildingPrice->service_id,
                    'price' => $buildingPrice->price,
                ]);
            }

            AdminActivityLogger::write($admin, 'Tạo phòng', Room::class, $room->id, null, $room->fresh()->toArray(), $request);

            DB::commit();

            $room->load(['building', 'roomType', 'images', 'assets.assetTemplate']);

            return ApiResponse::responseJson(true, "Thêm phòng thành công", 201, $room, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            foreach ($uploadedImagePaths as $path) {
                ImageHelper::delete($path);
            }
            return ApiResponse::responseJson(false, 'Lỗi server: ' . $e->getMessage(), 500, null, 500);
        }
    }
    /**
     * Display the specified resource.
     */
    public function show(string $id, Request $request)
    {
        try {
            $admin = $request->user();

            $room = Room::with("building")->with("roomType")->with('images')->with('assets.assetTemplate')->find($id);
            if (!$room) {
                return ApiResponse::responseJson(false, 'Không thể tìm thấy phòng', 404, null, 404);
            }
            if (!AdminScope::ensureBuildingAccess($admin, $room->building_id)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền truy cập vào phòng này', 403, null, 403);
            }
            return ApiResponse::responseJson(true, "Chi tiết phòng", 200, $room, 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Lỗi server: ' . $e->getMessage(), 500, null, 500);
        }
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(RoomRequest $request, $id)
    {
        $room = Room::find($id);
        if (!$room) {
            return ApiResponse::responseJson(false, 'Không tìm thấy thông tin phòng trọ này.', 404, null, 404);
        }

        $admin = $request->user();

        if (!AdminScope::isSuperAdmin($admin)) {
            return ApiResponse::responseJson(false, 'Bạn không có quyền truy cập vào tòa nhà này', 403, null, 403);
        }

        $uploadedImagePaths = [];
        DB::beginTransaction();

        try {
            $validatedData = $request->validated();
            $oldData = $room->fresh()->toArray();

            $room->update([
                'building_id'   => $validatedData['building_id'],
                'room_type_id'  => $validatedData['room_type_id'],
                'room_number'   => $validatedData['room_number'],
                'floor'         => $validatedData['floor'],
                'area_m2'       => $validatedData['area_m2'],
                'base_price'    => $validatedData['base_price'],
                'max_occupants' => $validatedData['max_occupants'],
                'status'        => $validatedData['status'] ?? $room->status,
                'description'   => $validatedData['description'] ?? $room->description,
            ]);

            // Xử lý ảnh: nếu frontend gửi ảnh mới => xóa ảnh cũ, thay toàn bộ
            if ($request->hasFile('images')) {
                $oldImages = $room->images()->get();

                $isPrimary = true;
                $sortOrder = 1;
                foreach ($request->file('images') as $image) {
                    $path = ImageHelper::create($image, 'rooms');
                    $uploadedImagePaths[] = $path;
                    $room->images()->create([
                        'image_path'  => $path,
                        'is_primary'  => $isPrimary ? 1 : 0,
                        'sort_order'  => $sortOrder,
                        'status'      => 1,
                        'uploaded_by' => $admin->id,
                    ]);
                    $isPrimary = false;
                    $sortOrder++;
                }

                // Xóa ảnh cũ sau khi upload ảnh mới thành công
                foreach ($oldImages as $oldImage) {
                    if ($oldImage->image_path) {
                        ImageHelper::delete($oldImage->image_path);
                    }
                }
                $room->images()->whereIn('id', $oldImages->pluck('id'))->delete();
            }

            // Xử lý tài sản
            if ($request->has('assets') && is_array($request->assets)) {
                $inputTemplateIds = collect($request->assets)->pluck('template_id')->toArray();

                // Xóa các asset không còn trong danh sách gửi lên
                $room->assets()->whereNotIn('asset_template_id', $inputTemplateIds)->delete();

                foreach ($request->assets as $assetInput) {
                    $room->assets()->updateOrCreate(
                        ['asset_template_id' => $assetInput['template_id']],
                        [
                            'quantity' => $assetInput['quantity'],
                            'price'    => $assetInput['price'] ?? null,
                            'note'     => $assetInput['note'] ?? 'Cập nhật thông tin phòng.',
                        ]
                    );
                }
            } else {
                // Không gửi assets => xóa hết
                $room->assets()->delete();
            }

            AdminActivityLogger::write($admin, 'Cập nhật phòng', Room::class, $room->id, $oldData, $room->fresh()->toArray(), $request);

            DB::commit();

            $room->load(['building', 'roomType', 'images', 'assets.assetTemplate', 'meterDevices']);

            return ApiResponse::responseJson(true, "Cập nhật thông tin phòng trọ thành công.", 200, $room, 200);
        } catch (\Exception $e) {
            DB::rollBack();
            foreach ($uploadedImagePaths as $path) {
                ImageHelper::delete($path);
            }
            return ApiResponse::responseJson(false, 'Lỗi hệ thống: ' . $e->getMessage(), 500, null, 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id, Request $request)
    {
        try {
            $admin = $request->user();

            $room = Room::find($id);
            if (!$room) {
                return ApiResponse::responseJson(false, 'Không thể tìm thấy phòng', 404, null, 404);
            }
            if (!AdminScope::isSuperAdmin($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền truy cập vào tòa nhà này', 403, null, 403);
            }
            $hasData = $room->contracts()->exists()
                || $room->meterDevices()->exists()
                || $room->invoices()->exists()
                || $room->maintenanceRequests()->exists()
                || $room->expenses()->exists()
                || $room->outgoingMovements()->exists()
                || $room->incomingMovements()->exists()
                || $room->notifications()->exists()
                || $room->current_occupants > 0;

            if ($hasData) {
                return ApiResponse::responseJson(false, 'Không thể xóa phòng này vì có dữ liệu liên quan ', 400, null, 400);
            }
            $oldData = $room->fresh()->toArray();
            $roomImages = $room->images;
            $room->assets()->delete();
            $room->images()->delete();
            $room->delete();
            AdminActivityLogger::write($admin, 'Xóa phòng', Room::class, (int) $id, $oldData, null, $request);
            foreach ($roomImages as $image) {
                if ($image->image_path) {
                    ImageHelper::delete($image->image_path);
                }
            }
            return ApiResponse::responseJson(true, "Xóa phòng thành công", 200, null, 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Lỗi server: ' . $e->getMessage(), 500, null, 500);
        }
    }

    public function updateStatus(Request $request, string $id)
    {
        try {
            $admin = $request->user();
            $room = Room::find($id);
            if (!$room) {
                return ApiResponse::responseJson(false, 'Không thể tìm thấy phòng', 404, null, 404);
            }
            $oldData = $room->fresh()->toArray();
            $update_status_for_room = $room->update([
                'status' => $room->status == 1 ? 3 : 1
            ]);
            AdminActivityLogger::write($admin, 'Cập nhật trạng thái phòng', Room::class, $room->id, $oldData, $room->fresh()->toArray(), $request);
            return ApiResponse::responseJson(true, "Cập nhật trạng thái phòng thành công", 200, null, 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Lỗi server: ' . $e->getMessage(), 500, null, 500);
        }
    }

    public function transferTenant(TranferSingleTenantRequest $request)
    {
        try {
            $result = DB::transaction(fn(): array => $this->scheduleRoomTransfer($request->validated(), $request->user(), $request));
            $result = $this->executeTodayTransferIfNeeded($result);

            $message = $result['executed_immediately'] ?? false
                ? 'Chuyển phòng trong ngày đã được xử lý thành công'
                : 'Lên lịch chuyển phòng thành công';

            return ApiResponse::responseJson(true, $message, 201, new RoomTransferResultResource($result), 201);
        } catch (ValidationException $e) {
            $firstError = collect($e->errors())->flatten()->first() ?? $e->getMessage();
            return ApiResponse::responseJson(false, $firstError, 422, $e->errors(), 422);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Lỗi server: ' . $e->getMessage(), 500, null, 500);
        }
    }

    private function scheduleRoomTransfer(array $validated, Admin $admin, Request $request): array
    {
        $tenantIds = $this->tenantIds($validated);
        $movementDate = Carbon::parse($validated['movement_date'])->startOfDay();
        $activeRows = $this->activeRowsForTenants($tenantIds);

        $this->assertAllTenantsHaveSameActiveContract($tenantIds, $activeRows);

        $sourceContract = $this->sourceContract((int) $activeRows->first()->contract_id);
        $fromRoom = $sourceContract->room;
        $toRoom = Room::query()->with('building')->lockForUpdate()->find((int) $validated['to_room_id']);

        $this->assertScheduleIsAllowed($admin, $tenantIds, $sourceContract, $fromRoom, $toRoom, $movementDate);
        $this->assertNoPendingTransfers($tenantIds);
        $this->assertNoPendingTransferForSourceContract((int) $sourceContract->id);

        $transferCode = $this->generateTransferCode($movementDate);
        $payload = $this->scheduledPayload($validated, $tenantIds, $movementDate, $toRoom);
        $movements = $this->createPendingMovements($transferCode, $sourceContract, $fromRoom, $toRoom, $activeRows, $payload, $admin);
        $notifications = $this->createTransferScheduledNotifications($movements, $sourceContract, $toRoom, $admin)
            ->merge($this->createDueUtilityCutoffNotifications($movements, $sourceContract, $fromRoom, $admin));

        AdminActivityLogger::write(
            $admin,
            'Lên lịch chuyển phòng',
            RoomMovement::class,
            $movements->first()->id,
            null,
            ['transfer_code' => $transferCode, 'payload' => $payload],
            $request,
        );

        DB::afterCommit(fn(): mixed => $this->broadcastNotifications($notifications));

        return [
            'transfer_code' => $transferCode,
            'movement' => $movements->first()->fresh($this->transferResultRelations()),
            'movements' => RoomMovement::query()->where('transfer_code', $transferCode)->with($this->transferResultRelations())->get(),
            'status' => RoomMovement::STATUS_PENDING,
            'scheduled_payload' => $payload,
        ];
    }

    private function executeTodayTransferIfNeeded(array $result): array
    {
        $movementDate = Carbon::parse($result['scheduled_payload']['movement_date'] ?? null)->startOfDay();

        if (! $movementDate->isSameDay(now('Asia/Ho_Chi_Minh')->startOfDay())) {
            return $result;
        }

        $executeResult = app(ExecuteScheduledRoomTransfers::class)->executeTransferCode((string) $result['transfer_code']);
        $movements = RoomMovement::query()
            ->where('transfer_code', $result['transfer_code'])
            ->with($this->transferResultRelations())
            ->orderBy('id')
            ->get();

        return array_merge($result, [
            'movement' => $movements->first(),
            'movements' => $movements,
            'status' => $executeResult['status'] === 'executed' ? RoomMovement::STATUS_EXECUTED : RoomMovement::STATUS_BLOCKED,
            'new_contract' => isset($executeResult['destination_contract_id'])
                ? Contract::query()->with(['room.building'])->find($executeResult['destination_contract_id'])
                : null,
            'deposit' => $executeResult['settlement'] ?? null,
            'execute_result' => $executeResult,
            'executed_immediately' => $executeResult['status'] === 'executed',
            'blocked_immediately' => $executeResult['status'] === 'blocked',
        ]);
    }

    private function tenantIds(array $validated): array
    {
        return collect($validated['tenant_ids'] ?? [$validated['tenant_id']])
            ->filter(fn($tenantId): bool => filled($tenantId))
            ->map(fn($tenantId): int => (int) $tenantId)
            ->unique()
            ->values()
            ->all();
    }

    private function activeRowsForTenants(array $tenantIds): EloquentCollection
    {
        return ContractTenant::query()
            ->whereIn('tenant_id', $tenantIds)
            ->where('is_staying', true)
            ->whereNull('leave_date')
            ->with('tenant')
            ->lockForUpdate()
            ->get();
    }

    private function assertAllTenantsHaveSameActiveContract(array $tenantIds, EloquentCollection $activeRows): void
    {
        if ($activeRows->count() !== count($tenantIds)) {
            throw ValidationException::withMessages(['tenant_ids' => 'Một hoặc nhiều khách thuê hiện không có hợp đồng đang ở.']);
        }

        if ($activeRows->pluck('contract_id')->unique()->count() !== 1) {
            throw ValidationException::withMessages(['tenant_ids' => 'Các khách thuê cần chuyển phải thuộc cùng một hợp đồng hiện tại.']);
        }
    }

    private function sourceContract(int $contractId): Contract
    {
        $contract = Contract::query()
            ->with(['room.building', 'contractTenants.tenant', 'contractVehicles.vehicle', 'depositTransactions'])
            ->lockForUpdate()
            ->find($contractId);

        if (! $contract || (int) $contract->status !== Contract::STATUS_ACTIVE) {
            throw ValidationException::withMessages(['tenant_ids' => 'Không tìm thấy hợp đồng cũ đang hiệu lực.']);
        }

        return $contract;
    }

    private function assertScheduleIsAllowed(Admin $admin, array $tenantIds, Contract $sourceContract, ?Room $fromRoom, ?Room $toRoom, Carbon $movementDate): void
    {
        if (! $fromRoom || ! $toRoom) {
            throw ValidationException::withMessages(['to_room_id' => 'Không tìm thấy phòng cũ hoặc phòng đích.']);
        }

        if ((int) $fromRoom->id === (int) $toRoom->id) {
            throw ValidationException::withMessages(['to_room_id' => 'Phòng đích không được là phòng hiện tại.']);
        }

        if (! AdminScope::ensureBuildingAccess($admin, (int) $fromRoom->building_id) || ! AdminScope::ensureBuildingAccess($admin, (int) $toRoom->building_id)) {
            throw ValidationException::withMessages(['to_room_id' => 'Bạn không có quyền chuyển khách thuê giữa các tòa nhà này.']);
        }

        $message = $this->destinationValidationMessage($tenantIds, $toRoom);
        if ($message !== null) {
            throw ValidationException::withMessages(['to_room_id' => $message]);
        }

        if ($this->hasUnpaidOldDebt($sourceContract, $movementDate)) {
            throw ValidationException::withMessages(['invoice' => 'Hợp đồng/phòng cũ còn hóa đơn chưa thanh toán, vui lòng thanh toán hết nợ cũ trước khi lên lịch chuyển phòng.']);
        }
    }

    private function destinationValidationMessage(array $tenantIds, Room $toRoom): ?string
    {
        if ((int) $toRoom->status !== Room::STATUS_ACTIVE) {
            return 'Phòng đích đang không ở trạng thái cho thuê được.';
        }

        $destinationActiveContract = $this->activeDestinationContract($toRoom);
        $currentOccupants = $destinationActiveContract ? $this->activeTenantCount($destinationActiveContract->id) : (int) $toRoom->current_occupants;

        if ((int) $toRoom->max_occupants > 0 && $currentOccupants + count($tenantIds) > (int) $toRoom->max_occupants) {
            return 'Phòng đích đã vượt sức chứa tối đa, không thể chuyển vào.';
        }

        $tenants = Tenant::query()->whereIn('id', $tenantIds)->get();
        foreach ($tenants as $tenant) {
            if (! $toRoom->building?->allowsTenantGender($tenant->gender)) {
                return 'Giới tính khách thuê không phù hợp với chính sách giới tính của tòa nhà.';
            }
        }

        return null;
    }

    private function assertNoPendingTransfers(array $tenantIds): void
    {
        $hasPendingTransfer = RoomMovement::query()
            ->where('movement_type', RoomMovement::MOVEMENT_TYPE_TRANSFER)
            ->whereIn('tenant_id', $tenantIds)
            ->whereIn('status', [RoomMovement::STATUS_PENDING, RoomMovement::STATUS_BLOCKED])
            ->exists();

        if ($hasPendingTransfer) {
            throw ValidationException::withMessages(['tenant_ids' => 'Một hoặc nhiều khách thuê đã có lịch chuyển phòng chưa hoàn tất.']);
        }
    }

    private function assertNoPendingTransferForSourceContract(int $sourceContractId): void
    {
        $hasPendingTransfer = RoomMovement::query()
            ->where('movement_type', RoomMovement::MOVEMENT_TYPE_TRANSFER)
            ->where('source_contract_id', $sourceContractId)
            ->whereIn('status', [RoomMovement::STATUS_PENDING, RoomMovement::STATUS_BLOCKED])
            ->exists();

        if ($hasPendingTransfer) {
            throw ValidationException::withMessages(['tenant_ids' => 'Hợp đồng/phòng cũ đã có lịch chuyển phòng đang chờ quyết toán. Vui lòng xử lý lịch đó trước.']);
        }
    }

    private function hasUnpaidOldDebt(Contract $contract, Carbon $movementDate): bool
    {
        $finalInvoiceCutoffDate = $movementDate->copy()->subDay();

        return Invoice::query()
            ->where('contract_id', $contract->id)
            ->whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIALLY_PAID, Invoice::STATUS_OVERDUE])
            ->where(function ($query) use ($movementDate, $finalInvoiceCutoffDate): void {
                $query->where('billing_year', '<', $movementDate->year)
                    ->orWhere(function ($sameYearQuery) use ($movementDate): void {
                        $sameYearQuery->where('billing_year', $movementDate->year)
                            ->where('billing_month', '<', $movementDate->month);
                    });

                if ($finalInvoiceCutoffDate->year !== $movementDate->year || $finalInvoiceCutoffDate->month !== $movementDate->month) {
                    return;
                }

                $query->orWhere(function ($sameMonthFinalQuery) use ($finalInvoiceCutoffDate): void {
                    $sameMonthFinalQuery->where('billing_year', $finalInvoiceCutoffDate->year)
                        ->where('billing_month', $finalInvoiceCutoffDate->month)
                        ->whereDate('period_end', $finalInvoiceCutoffDate->toDateString());
                });
            })
            ->exists();
    }

    private function scheduledPayload(array $validated, array $tenantIds, Carbon $movementDate, Room $toRoom): array
    {
        return [
            'tenant_ids' => $tenantIds,
            'to_room_id' => (int) $toRoom->id,
            'movement_date' => $movementDate->toDateString(),
            'deposit_deduction_amount' => DecimalMoney::normalize($this->deductionAmount($validated)),
            'transfer_fee' => DecimalMoney::normalize($validated['transfer_fee'] ?? '0'),
            'new_deposit_amount' => DecimalMoney::normalize($validated['new_deposit_amount'] ?? $toRoom->base_price),
            'note' => $validated['note'] ?? null,
            'deduction_items' => $validated['deduction_items'] ?? [],
        ];
    }

    private function deductionAmount(array $validated): string
    {
        if (array_key_exists('deposit_deduction_amount', $validated)) {
            return DecimalMoney::normalize($validated['deposit_deduction_amount']);
        }

        return DecimalMoney::add(collect($validated['deduction_items'] ?? [])->pluck('amount')->all());
    }

    private function createPendingMovements(string $transferCode, Contract $sourceContract, Room $fromRoom, Room $toRoom, EloquentCollection $rows, array $payload, Admin $admin): Collection
    {
        return $rows->map(fn(ContractTenant $row): RoomMovement => RoomMovement::query()->create([
            'transfer_code' => $transferCode,
            'tenant_id' => $row->tenant_id,
            'contract_id' => $sourceContract->id,
            'source_contract_id' => $sourceContract->id,
            'destination_contract_id' => null,
            'from_room_id' => $fromRoom->id,
            'to_room_id' => $toRoom->id,
            'movement_type' => RoomMovement::MOVEMENT_TYPE_TRANSFER,
            'status' => RoomMovement::STATUS_PENDING,
            'movement_date' => Carbon::parse($payload['movement_date'])->toDateTimeString(),
            'old_room_final_amount' => '0.00',
            'transfer_fee' => $payload['transfer_fee'],
            'deposit_transfer_amount' => '0.00',
            'deposit_refund_amount' => '0.00',
            'deduction_amount' => $payload['deposit_deduction_amount'],
            'manual_refund_amount' => '0.00',
            'deposit_due_amount' => '0.00',
            'extra_charge_amount' => '0.00',
            'settlement_due_amount' => '0.00',
            'settlement_paid_amount' => '0.00',
            'settlement_payment_status' => RoomMovement::SETTLEMENT_PAYMENT_STATUS_PAID,
            'settlement_payment_references' => [],
            'note' => $payload['note'],
            'scheduled_payload' => $payload,
            'created_by' => $admin->id,
        ]));
    }

    private function activeDestinationContract(Room $toRoom): ?Contract
    {
        return Contract::query()
            ->with(['room.building', 'contractTenants.tenant', 'contractVehicles.vehicle', 'depositTransactions'])
            ->where('room_id', $toRoom->id)
            ->where('status', Contract::STATUS_ACTIVE)
            ->lockForUpdate()
            ->first();
    }

    private function activeTenantCount(int $contractId): int
    {
        return ContractTenant::query()
            ->where('contract_id', $contractId)
            ->where('is_staying', true)
            ->whereNull('leave_date')
            ->distinct('tenant_id')
            ->count('tenant_id');
    }

    private function createTransferScheduledNotifications(Collection $movements, Contract $sourceContract, Room $toRoom, Admin $admin): Collection
    {
        return $movements->map(fn(RoomMovement $movement): Notification => Notification::query()->create([
            'title' => 'Lịch chuyển phòng đã được tạo',
            'content' => "Bạn được lên lịch chuyển từ phòng {$sourceContract->room?->room_number} sang phòng {$toRoom->room_number} vào ngày " . Carbon::parse($movement->movement_date)->format('d/m/Y') . '.',
            'notification_type' => Notification::NOTIFICATION_TYPE_SYSTEM,
            'target_type' => Notification::TARGET_TYPE_TENANT,
            'building_id' => $toRoom->building_id,
            'room_id' => $toRoom->id,
            'tenant_id' => $movement->tenant_id,
            'published_at' => now(),
            'status' => Notification::STATUS_SENT,
            'created_by' => $admin->id,
        ]));
    }

    private function createDueUtilityCutoffNotifications(Collection $movements, Contract $sourceContract, Room $fromRoom, Admin $admin): Collection
    {
        $movement = $movements->first();
        $movementDate = Carbon::parse($movement->movement_date)->startOfDay();
        $cutoffDate = $movementDate->copy()->subDay();

        if ($cutoffDate->isFuture()) {
            return collect();
        }

        return collect([$this->createUtilityCutoffNotification($movement, $sourceContract, $fromRoom, $admin, $movementDate, $cutoffDate)]);
    }

    private function createUtilityCutoffNotification(RoomMovement $movement, Contract $sourceContract, Room $fromRoom, Admin $admin, Carbon $movementDate, Carbon $cutoffDate): Notification
    {
        $actionUrl = $this->utilityCutoffActionUrl($movement, $sourceContract, $fromRoom, $movementDate, $cutoffDate);

        return Notification::query()->firstOrCreate(
            [
                'title' => 'Cần chốt điện nước chuyển phòng',
                'action_url' => $actionUrl,
            ],
            [
                'content' => "Phòng {$fromRoom->room_number} có lịch chuyển phòng {$movement->transfer_code} ngày {$movementDate->format('d/m/Y')}. Vui lòng nhập chỉ số điện/nước đến ngày {$cutoffDate->format('d/m/Y')} và lập hóa đơn cuối cho hợp đồng cũ {$sourceContract->contract_code}.",
                'notification_type' => Notification::NOTIFICATION_TYPE_SYSTEM,
                'target_type' => Notification::TARGET_TYPE_ADMIN,
                'building_id' => $fromRoom->building_id,
                'room_id' => $fromRoom->id,
                'tenant_id' => null,
                'published_at' => now(),
                'status' => Notification::STATUS_SENT,
                'created_by' => $admin->id,
            ]
        );
    }

    private function utilityCutoffActionUrl(RoomMovement $movement, Contract $sourceContract, Room $fromRoom, Carbon $movementDate, Carbon $cutoffDate): string
    {
        return '/admin/meter-readings?' . http_build_query([
            'building_id' => $fromRoom->building_id,
            'billing_month' => $cutoffDate->month,
            'billing_year' => $cutoffDate->year,
            'room_id' => $fromRoom->id,
            'contract_id' => $sourceContract->id,
            'cutoff_date' => $cutoffDate->toDateString(),
            'transfer_code' => $movement->transfer_code,
        ]);
    }

    private function broadcastNotifications(Collection $notifications): void
    {
        $notifications->each(fn(Notification $notification): mixed => event(new NotificationSent($notification)));
    }

    private function generateTransferCode(Carbon $movementDate): string
    {
        $prefix = 'TRF-' . $movementDate->format('Y-m') . '-';
        $next = RoomMovement::query()
            ->where('transfer_code', 'like', $prefix . '%')
            ->lockForUpdate()
            ->count() + 1;

        do {
            $code = $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            $next++;
        } while (RoomMovement::query()->where('transfer_code', $code)->exists());

        return $code;
    }

    private function transferResultRelations(): array
    {
        return [
            'tenant:id,username,full_name,phone,email',
            'contract:id,contract_code,room_id,status,payment_status',
            'sourceContract:id,contract_code,room_id,status,payment_status',
            'destinationContract:id,contract_code,room_id,status,payment_status',
            'fromRoom:id,building_id,room_number,floor,status',
            'fromRoom.building:id,name,slug,manager_admin_id,status',
            'toRoom:id,building_id,room_number,floor,status',
            'toRoom.building:id,name,slug,manager_admin_id,status',
            'creator:id,username,full_name,email,role,status',
        ];
    }
}
