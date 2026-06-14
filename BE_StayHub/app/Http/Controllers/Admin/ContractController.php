<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AdminActivityLogger;
use App\Helpers\AdminScope;
use App\Helpers\ApiResponse;
use App\Helpers\ImageHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Contract\IndexRequest;
use App\Http\Requests\Admin\Contract\RegisterRequest;
use App\Http\Requests\Admin\Contract\StatusRequest;
use App\Http\Requests\Admin\Contract\UpdateRequest;
use App\Http\Requests\Admin\Contract\DepositTransactionRequest;
use App\Helpers\VietQRHelper;
use App\Http\Resources\Admin\ContractDetailResource;
use App\Http\Resources\Admin\ContractResource;
use App\Models\Admin;
use App\Models\Contract;
use App\Models\ContractDepositTransaction;
use App\Models\ContractTenant;
use App\Models\ContractVehicle;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\Vehicle;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ContractController extends Controller
{
    private const FILE_DISK = 'public';

    public function availableRooms(Request $request): JsonResponse
    {
        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canManageContracts($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xem danh sách phòng', 403, null, 403);
            }

            $buildingId = (int) $request->query('building_id');

            if ($buildingId <= 0) {
                return ApiResponse::responseJson(false, 'Vui lòng chọn tòa nhà', 422, null, 422);
            }

            if (! AdminScope::ensureBuildingAccess($admin, $buildingId)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xem phòng của tòa nhà này', 403, null, 403);
            }

            $rooms = Room::query()
                ->select(['id', 'building_id', 'room_number', 'status', 'base_price', 'max_occupants', 'current_occupants'])
                ->where('building_id', $buildingId)
                ->where('status', Room::STATUS_ACTIVE)
                ->whereDoesntHave('contracts', function (Builder $query): void {
                    $query->where('status', Contract::STATUS_ACTIVE);
                })
                ->orderBy('room_number')
                ->get();

            return ApiResponse::responseJson(true, 'Danh sách phòng khả dụng', 200, $rooms, 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function index(IndexRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canManageContracts($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xem danh sách hợp đồng', 403, null, 403);
            }

            if (isset($validated['building_id']) && ! AdminScope::ensureBuildingAccess($admin, (int) $validated['building_id'])) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xem hợp đồng của tòa nhà này', 403, null, 403);
            }

            if (isset($validated['room_id']) && ! $this->ensureRoomAccess($admin, (int) $validated['room_id'])) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xem hợp đồng của phòng này', 403, null, 403);
            }



            $contracts = $this->queryContracts($validated, $admin)->paginate($validated['per_page'] ?? 20);

            return ApiResponse::responseJson(true, 'Danh sách hợp đồng', 200, $this->paginatedResource($contracts), 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function store(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $uploadedPaths = [];

        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canManageContracts($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền tạo hợp đồng', 403, null, 403);
            }

            $contract = DB::transaction(function () use ($validated, $admin, $request, &$uploadedPaths): Contract {
                $status = (int) ($validated['status'] ?? Contract::STATUS_ACTIVE);

                $room = Room::query()->with('building:id,manager_admin_id,name')->lockForUpdate()->find((int) $validated['room_id']);

                if (! $room) {
                    $this->throwResponse('Không tìm thấy phòng ký hợp đồng', 404);
                }

                $this->assertRoomCanBeUsed($admin, $room);

                $validated['contract_code'] = $this->generateContractCode($room);

                $tenantPayloads = $this->normalizedTenantPayloads($validated, null);
                $tenantIds = collect($tenantPayloads)->pluck('tenant_id')->all();

                $this->assertTenantPayloads($admin, $tenantPayloads, $room, null, $status);

                $vehiclePayloads = $this->normalizedVehiclePayloads($validated, true);
                $this->assertVehiclePayloads($admin, $vehiclePayloads, $tenantIds, null, $status);

                $this->assertRoomContractAvailability($room->id, null, $status);
                $this->assertRoomCapacity($room, $tenantPayloads);

                $contract = Contract::query()->create($this->payload($validated, $admin, $status));
                $contractFiles = $this->storeContractFiles($request, $contract, $uploadedPaths);

                if ($contractFiles !== []) {
                    $contract->forceFill(['contract_files' => $contractFiles])->save();
                }

                $this->syncContractTenants($contract, $tenantPayloads, $admin, true);
                $this->syncContractVehicles($contract, $vehiclePayloads, true);

                $depositAmountCents = $this->decimalToCents($contract->deposit_amount);
                $isDepositPaid = isset($validated['is_deposit_paid']) ? (bool) $validated['is_deposit_paid'] : true;
                if ($depositAmountCents > 0 && $isDepositPaid) {
                    $contract->depositTransactions()->create([
                        'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_COLLECT,
                        'amount' => $contract->deposit_amount,
                        'transaction_date' => now()->toDateString(),
                        'payment_method' => $validated['deposit_payment_method'] ?? ContractDepositTransaction::PAYMENT_METHOD_BANK_TRANSFER,
                        'note' => 'Thu cọc khi tạo hợp đồng',
                        'created_by' => $admin->id,
                    ]);
                }

                $this->refreshRoomOccupants($room->id);
                $this->loadDetailRelations($contract);

                AdminActivityLogger::write($admin, 'create_contract', Contract::class, $contract->id, null, $contract->fresh()->toArray(), $request);

                return $contract;
            });

            // Dispatch notifications to tenants of the new contract
            $this->notifyTenantsOfNewContract($contract, $admin->id);


            return ApiResponse::responseJson(true, 'Tạo hợp đồng thành công', 201, new ContractDetailResource($contract), 201);
        } catch (HttpResponseException $e) {
            $this->deleteDiskFiles($uploadedPaths);
            return $e->getResponse();
        } catch (\Exception $e) {
            $this->deleteDiskFiles($uploadedPaths);
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function show(Request $request, int $contract): JsonResponse
    {
        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canManageContracts($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xem hợp đồng', 403, null, 403);
            }

            $contractModel = $this->accessibleQuery($admin)
                ->select($this->detailColumns())
                ->with($this->detailRelations())
                ->withCount($this->detailCounts())
                ->find($contract);

            if (! $contractModel) {
                return ApiResponse::responseJson(false, 'Không tìm thấy hợp đồng', 404, null, 404);
            }

            return ApiResponse::responseJson(true, 'Chi tiết hợp đồng', 200, new ContractDetailResource($contractModel), 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function update(UpdateRequest $request, int $contract): JsonResponse
    {
        $validated = $request->validated();
        $uploadedPaths = [];
        $pathsToDelete = [];

        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canManageContracts($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền cập nhật hợp đồng', 403, null, 403);
            }

            $contractModel = $this->accessibleQuery($admin)->findOrFail($contract);

            $updatedContract = DB::transaction(function () use ($validated, $contractModel, $admin, $request, &$uploadedPaths, &$pathsToDelete): Contract {
                if ($this->isTerminalContract($contractModel) && $this->hasStructuralUpdate($validated)) {
                    $this->throwResponse('Hợp đồng đã thanh lý hoặc đã hủy chỉ được cập nhật ghi chú/file, không được sửa dữ liệu nghiệp vụ.', 422);
                }

                unset($validated['contract_code']);

                $oldRoomId = (int) $contractModel->room_id;
                $oldData = $contractModel->load($this->detailRelations())->loadCount($this->detailCounts())->toArray();
                $status = (int) $contractModel->status;
                $roomId = (int) ($validated['room_id'] ?? $contractModel->room_id);
                $room = Room::query()->with('building:id,manager_admin_id,name')->lockForUpdate()->find($roomId);

                if (! $room) {
                    $this->throwResponse('Không tìm thấy phòng ký hợp đồng', 404);
                }

                if (! AdminScope::ensureBuildingAccess($admin, (int) $room->building_id)) {
                    $this->throwResponse('Bạn không có quyền thao tác hợp đồng của tòa nhà này.', 403);
                }

                if ($this->hasStructuralUpdate($validated) && (int) $room->status !== Room::STATUS_ACTIVE) {
                    $this->throwResponse('Chỉ có thể cập nhật dữ liệu nghiệp vụ cho phòng đang hoạt động.', 422);
                }

                if ($roomId !== $oldRoomId) {
                    $validated['contract_code'] = $this->generateContractCode($room);
                }

                $payload = $this->payload($validated, $admin, $status, true);

                if (array_key_exists('tenants', $validated)) {
                    $tenantPayloads = $this->normalizedTenantPayloads($validated, $contractModel);
                    $tenantIds = collect($tenantPayloads)->pluck('tenant_id')->all();
                    $this->assertTenantPayloads($admin, $tenantPayloads, $room, $contractModel->id, $status);
                    $this->assertRoomCapacity($room, $tenantPayloads, $contractModel->id);
                } else {
                    $tenantPayloads = null;
                    $currentTenantPayloads = $this->currentTenantPayloads($contractModel);
                    $tenantIds = collect($currentTenantPayloads)->pluck('tenant_id')->all();

                    if ($this->hasStructuralUpdate($validated) || $status === Contract::STATUS_ACTIVE) {
                        $this->assertTenantPayloads($admin, $currentTenantPayloads, $room, $contractModel->id, $status);
                        $this->assertRoomCapacity($room, $currentTenantPayloads, $contractModel->id);
                    }
                }

                $startDate = $payload['start_date'] ?? $contractModel->start_date?->toDateString();
                if (array_key_exists('vehicles', $validated)) {
                    $vehiclePayloads = $this->normalizedVehiclePayloads($validated, false);
                    $this->assertVehiclePayloads($admin, $vehiclePayloads, $tenantIds, $contractModel->id, $status);
                } else {
                    $vehiclePayloads = null;
                    $currentVehiclePayloads = $this->currentVehiclePayloads($contractModel);

                    if (($this->hasStructuralUpdate($validated) || $status === Contract::STATUS_ACTIVE) && $currentVehiclePayloads !== []) {
                        $this->assertVehiclePayloads($admin, $currentVehiclePayloads, $tenantIds, $contractModel->id, $status);
                    }
                }

                $this->assertRoomContractAvailability($room->id, $contractModel->id, $status);
                $this->assertContractDates($contractModel, $payload);

                if ($payload !== []) {
                    $contractModel->fill($payload)->save();
                }



                $pathsToDelete = $this->contractFilesToDelete($contractModel, $validated['delete_contract_files'] ?? []);
                $nextFiles = collect($contractModel->contract_files ?? [])
                    ->reject(fn (string $path): bool => in_array($path, $pathsToDelete, true))
                    ->merge($this->storeContractFiles($request, $contractModel, $uploadedPaths))
                    ->values()
                    ->all();

                if ($nextFiles !== ($contractModel->contract_files ?? [])) {
                    $contractModel->forceFill(['contract_files' => $nextFiles])->save();
                }

                if (is_array($tenantPayloads)) {
                    $this->syncContractTenants($contractModel, $tenantPayloads, $admin, false);
                }

                if (is_array($vehiclePayloads)) {
                    $this->syncContractVehicles($contractModel, $vehiclePayloads, false);
                }

                $this->refreshRoomOccupants((int) $contractModel->room_id);

                if ($oldRoomId !== (int) $contractModel->room_id) {
                    $this->refreshRoomOccupants($oldRoomId);
                }

                $contractModel->unsetRelations();
                $this->loadDetailRelations($contractModel);

                AdminActivityLogger::write($admin, 'update_contract', Contract::class, $contractModel->id, $oldData, $contractModel->toArray(), $request);

                return $contractModel;
            });

            $this->deleteDiskFiles($pathsToDelete);

            return ApiResponse::responseJson(true, 'Cập nhật hợp đồng thành công', 200, new ContractDetailResource($updatedContract), 200);
        } catch (HttpResponseException $e) {
            $this->deleteDiskFiles($uploadedPaths);
            return $e->getResponse();
        } catch (\Exception $e) {
            $this->deleteDiskFiles($uploadedPaths);
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function updateStatus(StatusRequest $request, int $contract): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canManageContracts($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền đổi trạng thái hợp đồng', 403, null, 403);
            }

            $contractModel = $this->accessibleQuery($admin)->findOrFail($contract);

            $updatedContract = DB::transaction(function () use ($validated, $contractModel, $admin, $request): Contract {
                $currentStatus = (int) $contractModel->status;
                $nextStatus = (int) $validated['status'];

                $this->assertStatusTransition($currentStatus, $nextStatus);

                if (in_array($nextStatus, [Contract::STATUS_LIQUIDATED, Contract::STATUS_CANCELLED], true) && blank($validated['actual_end_date'] ?? null)) {
                    $this->throwResponse('Vui lòng nhập ngày kết thúc thực tế khi thanh lý hoặc hủy hợp đồng.', 422);
                }

                if (filled($validated['actual_end_date'] ?? null) && $validated['actual_end_date'] < $contractModel->start_date?->toDateString()) {
                    $this->throwResponse('Ngày kết thúc thực tế phải lớn hơn hoặc bằng ngày bắt đầu hợp đồng.', 422);
                }

                if ($nextStatus === Contract::STATUS_ACTIVE) {
                    $room = Room::query()->with('building:id,manager_admin_id,name')->lockForUpdate()->find((int) $contractModel->room_id);
                    $this->assertRoomCanBeUsed($admin, $room);
                    $tenantPayloads = $this->currentTenantPayloads($contractModel);
                    $vehiclePayloads = $this->currentVehiclePayloads($contractModel);
                    $this->assertRoomContractAvailability((int) $contractModel->room_id, $contractModel->id, $nextStatus);
                    $this->assertRoomCapacity($room, $tenantPayloads, $contractModel->id);
                    $this->assertTenantPayloads($admin, $tenantPayloads, $room, $contractModel->id, $nextStatus);
                    $this->assertVehiclePayloads($admin, $vehiclePayloads, collect($tenantPayloads)->pluck('tenant_id')->all(), $contractModel->id, $nextStatus);
                }

                $payload = [
                    'status' => $nextStatus,
                ];

                if (array_key_exists('actual_end_date', $validated)) {
                    $payload['actual_end_date'] = $validated['actual_end_date'];
                }

                if (filled($validated['note'] ?? null)) {
                    $payload['note'] = trim((string) $validated['note']);
                }

                $oldData = $contractModel->toArray();
                $contractModel->forceFill($payload)->save();

                if (in_array($nextStatus, [Contract::STATUS_EXPIRED, Contract::STATUS_LIQUIDATED, Contract::STATUS_CANCELLED], true)) {
                    $endDate = $validated['actual_end_date'] ?? now()->toDateString();
                    $this->closeActiveContractRows($contractModel, $endDate);
                }

                $this->refreshRoomOccupants((int) $contractModel->room_id);
                $contractModel->unsetRelations();
                $this->loadDetailRelations($contractModel);

                AdminActivityLogger::write($admin, 'update_contract_status', Contract::class, $contractModel->id, $oldData, $contractModel->toArray(), $request);

                return $contractModel;
            });

            return ApiResponse::responseJson(true, 'Cập nhật trạng thái hợp đồng thành công', 200, new ContractDetailResource($updatedContract), 200);
        } catch (HttpResponseException $e) {
            return $e->getResponse();
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function destroy(Request $request, int $contract): JsonResponse
    {
        $pathsToDelete = [];

        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canManageContracts($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xóa hợp đồng', 403, null, 403);
            }

            $roomId = null;

            DB::transaction(function () use ($contract, $admin, $request, &$pathsToDelete, &$roomId): void {
                $contractModel = $this->accessibleQuery($admin)
                    ->withCount($this->deleteBlockingCounts())
                    ->lockForUpdate()
                    ->find($contract);

                if (! $contractModel) {
                    $this->throwResponse('Không tìm thấy hợp đồng', 404);
                }

                if (! in_array((int) $contractModel->status, [Contract::STATUS_CANCELLED], true)) {
                    $this->throwResponse('Chỉ có thể xóa hợp đồng đã hủy.', 422);
                }

                if ($this->hasDeleteBlockingData($contractModel)) {
                    $this->throwResponse('Không thể xóa hợp đồng đã phát sinh hóa đơn, giao dịch cọc hoặc lịch sử chuyển phòng.', 422);
                }

                $oldData = $contractModel->load($this->detailRelations())->toArray();
                $pathsToDelete = array_values(array_filter($contractModel->contract_files ?? []));
                $roomId = (int) $contractModel->room_id;

                $contractModel->contractTenants()->delete();
                $contractModel->contractVehicles()->delete();
                $contractModel->delete();

                $this->refreshRoomOccupants($roomId);
                AdminActivityLogger::write($admin, 'delete_contract', Contract::class, $contractModel->id, $oldData, null, $request);
            });

            $this->deleteDiskFiles($pathsToDelete);

            return ApiResponse::responseJson(true, 'Xóa hợp đồng thành công', 200, null, 200);
        } catch (HttpResponseException $e) {
            return $e->getResponse();
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function renew(RegisterRequest $request, int $contract): JsonResponse
    {
        $validated = $request->validated();
        $uploadedPaths = [];

        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canManageContracts($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền gia hạn hợp đồng', 403, null, 403);
            }

            $oldContract = $this->accessibleQuery($admin)->findOrFail($contract);

            $newContract = DB::transaction(function () use ($validated, $oldContract, $admin, $request, &$uploadedPaths): Contract {
                
                if (! in_array((int) $oldContract->status, [Contract::STATUS_ACTIVE, Contract::STATUS_EXPIRED], true)) {
                    $this->throwResponse('Chỉ có thể gia hạn hợp đồng đang hiệu lực hoặc đã hết hạn.', 422);
                }

                $room = Room::query()->with('building:id,manager_admin_id,name')->lockForUpdate()->find((int) $validated['room_id']);
                if (! $room) {
                    $this->throwResponse('Không tìm thấy phòng ký hợp đồng', 404);
                }

                $this->assertRoomCanBeUsed($admin, $room);

                
                $parentContractId = $oldContract->parent_contract_id ?? $oldContract->id;
                $validated['parent_contract_id'] = $parentContractId;
                $validated['renew_from_contract_id'] = $oldContract->id;

                
                $validated['contract_code'] = $this->generateContractCode($room);

                $status = Contract::STATUS_ACTIVE;
                $validated['status'] = $status;

                $tenantPayloads = $this->normalizedTenantPayloads($validated, null);
                $tenantIds = collect($tenantPayloads)->pluck('tenant_id')->all();

                $this->assertTenantPayloads($admin, $tenantPayloads, $room, $oldContract->id, $status);

                $vehiclePayloads = $this->normalizedVehiclePayloads($validated, true);
                $this->assertVehiclePayloads($admin, $vehiclePayloads, $tenantIds, $oldContract->id, $status);

                
                $oldContractActualEndDate = date('Y-m-d', strtotime($validated['start_date'] . ' - 1 day'));
                $oldContract->forceFill([
                    'status' => Contract::STATUS_EXPIRED,
                    'actual_end_date' => $oldContractActualEndDate,
                ])->save();

                $this->closeActiveContractRows($oldContract, $oldContractActualEndDate);

                
                $contract = Contract::query()->create($this->payload($validated, $admin, $status));
                $contractFiles = $this->storeContractFiles($request, $contract, $uploadedPaths);

                if ($contractFiles !== []) {
                    $contract->forceFill(['contract_files' => $contractFiles])->save();
                }

                $this->syncContractTenants($contract, $tenantPayloads, $admin, true);
                $this->syncContractVehicles($contract, $vehiclePayloads, true);

                
                $oldBalanceCents = $this->depositBalanceCents($oldContract);
                $newDepositAmountCents = $this->decimalToCents($contract->deposit_amount);

                if ($oldBalanceCents > 0 && $newDepositAmountCents > 0) {
                    $transferAmountCents = min($oldBalanceCents, $newDepositAmountCents);
                    $transferAmount = number_format($transferAmountCents / 100, 2, '.', '');

                    
                    $oldContract->depositTransactions()->create([
                        'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_DEDUCT,
                        'amount' => $transferAmount,
                        'transaction_date' => now()->toDateString(),
                        'payment_method' => ContractDepositTransaction::PAYMENT_METHOD_BANK_TRANSFER,
                        'note' => "Kết chuyển cọc sang hợp đồng gia hạn ID {$contract->id}",
                        'created_by' => $admin->id,
                    ]);

                    
                    $contract->depositTransactions()->create([
                        'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_TRANSFER,
                        'amount' => $transferAmount,
                        'transaction_date' => now()->toDateString(),
                        'payment_method' => ContractDepositTransaction::PAYMENT_METHOD_BANK_TRANSFER,
                        'note' => "Nhận cọc chuyển từ hợp đồng cũ ID {$oldContract->id}",
                        'created_by' => $admin->id,
                    ]);

                    
                    $remainderCents = $newDepositAmountCents - $transferAmountCents;
                    if ($remainderCents > 0) {
                        $remainderAmount = number_format($remainderCents / 100, 2, '.', '');
                        $contract->depositTransactions()->create([
                            'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_COLLECT,
                            'amount' => $remainderAmount,
                            'transaction_date' => now()->toDateString(),
                            'payment_method' => ContractDepositTransaction::PAYMENT_METHOD_BANK_TRANSFER,
                            'note' => 'Tự động thu phần cọc thiếu khi gia hạn hợp đồng',
                            'created_by' => $admin->id,
                        ]);
                    }
                } elseif ($newDepositAmountCents > 0) {
                    
                    $contract->depositTransactions()->create([
                        'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_COLLECT,
                        'amount' => $contract->deposit_amount,
                        'transaction_date' => now()->toDateString(),
                        'payment_method' => ContractDepositTransaction::PAYMENT_METHOD_BANK_TRANSFER,
                        'note' => 'Tự động thu cọc khi tạo hợp đồng gia hạn',
                        'created_by' => $admin->id,
                    ]);
                }

                $this->refreshRoomOccupants($room->id);
                $this->refreshRoomOccupants((int) $oldContract->room_id);
                $this->loadDetailRelations($contract);

                AdminActivityLogger::write($admin, 'renew_contract', Contract::class, $contract->id, null, $contract->toArray(), $request);

                return $contract;
            });

            // Dispatch notifications to tenants of the renewed contract
            $this->notifyTenantsOfNewContract($newContract, $admin->id);

            return ApiResponse::responseJson(true, 'Gia hạn hợp đồng thành công', 201, new ContractDetailResource($newContract), 201);
        } catch (HttpResponseException $e) {
            return $e->getResponse();
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    public function addDepositTransaction(DepositTransactionRequest $request, int $contract): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            if (! $admin || ! $this->canManageContracts($admin)) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền thêm giao dịch cọc', 403, null, 403);
            }

            $contractModel = $this->accessibleQuery($admin)->findOrFail($contract);

            $newTransaction = DB::transaction(function () use ($validated, $contractModel, $admin): ContractDepositTransaction {
                $currentBalanceCents = $this->depositBalanceCents($contractModel);
                $depositLimitCents = $this->decimalToCents($contractModel->deposit_amount);

                
                $this->assertDepositTransactions([$validated], $currentBalanceCents, $depositLimitCents);

                
                return $contractModel->depositTransactions()->create([
                    'transaction_type' => (int) $validated['transaction_type'],
                    'amount' => $this->normalizeDecimal($validated['amount']),
                    'transaction_date' => $validated['transaction_date'],
                    'payment_method' => (int) $validated['payment_method'],
                    'note' => $validated['note'] ?? null,
                    'created_by' => $admin->id,
                ]);
            });

            
            $contractModel->unsetRelations();
            $this->loadDetailRelations($contractModel);

            AdminActivityLogger::write($admin, 'add_deposit_transaction', Contract::class, $contractModel->id, null, $newTransaction->toArray(), $request);

            return ApiResponse::responseJson(true, 'Ghi nhận giao dịch cọc thành công', 201, new ContractDetailResource($contractModel), 201);
        } catch (HttpResponseException $e) {
            return $e->getResponse();
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    private function generateContractCode(Room $room): string
    {
        $buildingSlug = Str::upper(Str::slug($room->building->name ?? 'BUILDING'));
        $roomSlug = Str::upper(Str::slug($room->room_number ?? $room->slug ?? 'ROOM'));
        $baseCode = "HD-{$buildingSlug}-{$roomSlug}";

        $code = $baseCode;
        $counter = 1;

        while (Contract::query()->where('contract_code', $code)->exists()) {
            $counter++;
            $code = "{$baseCode}-{$counter}";
        }

        return $code;
    }

    private function queryContracts(array $validated, Admin $admin): Builder
    {
        $keyword = trim($validated['keyword'] ?? '');
        $escapedKeyword = '%'.addcslashes($keyword, '\\%_').'%';

        return $this->accessibleQuery($admin)
            ->select($this->listColumns())
            ->with($this->listRelations())
            ->withCount($this->listCounts())
            ->when($keyword !== '', fn (Builder $query): Builder => $query->where(function (Builder $keywordQuery) use ($escapedKeyword): void {
                $keywordQuery->where('contract_code', 'like', $escapedKeyword)
                    ->orWhereHas('room', fn (Builder $roomQuery): Builder => $roomQuery->where('room_number', 'like', $escapedKeyword))
                    ->orWhereHas('room.building', fn (Builder $buildingQuery): Builder => $buildingQuery->where('name', 'like', $escapedKeyword))
                    ->orWhereHas('tenants', function (Builder $tenantQuery) use ($escapedKeyword): void {
                        $tenantQuery->where('full_name', 'like', $escapedKeyword)
                            ->orWhere('phone', 'like', $escapedKeyword)
                            ->orWhere('email', 'like', $escapedKeyword)
                            ->orWhere('identity_number', 'like', $escapedKeyword);
                    });
            }))
            ->when(isset($validated['status']), fn (Builder $query): Builder => $query->where('status', (int) $validated['status']))
            ->when(isset($validated['building_id']), fn (Builder $query): Builder => $query->whereHas('room', fn (Builder $roomQuery): Builder => $roomQuery->where('building_id', (int) $validated['building_id'])))
            ->when(isset($validated['room_id']), fn (Builder $query): Builder => $query->where('room_id', (int) $validated['room_id']))
            ->when(AdminScope::isSuperAdmin($admin) && isset($validated['created_by']), fn (Builder $query): Builder => $query->where('created_by', (int) $validated['created_by']))
            ->when(isset($validated['start_date_from']), fn (Builder $query): Builder => $query->whereDate('start_date', '>=', $validated['start_date_from']))
            ->when(isset($validated['start_date_to']), fn (Builder $query): Builder => $query->whereDate('start_date', '<=', $validated['start_date_to']))
            ->when(isset($validated['end_date_from']), fn (Builder $query): Builder => $query->whereDate('end_date', '>=', $validated['end_date_from']))
            ->when(isset($validated['end_date_to']), fn (Builder $query): Builder => $query->whereDate('end_date', '<=', $validated['end_date_to']))
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    private function accessibleQuery(Admin $admin): Builder
    {
        $query = Contract::query();

        if (AdminScope::isSuperAdmin($admin)) {
            return $query;
        }

        if (AdminScope::isBuildingManager($admin)) {
            return $query->whereHas('room', function (Builder $roomQuery) use ($admin): void {
                AdminScope::applyBuildingScope($roomQuery, $admin, 'building_id');
            });
        }

        return $query->whereRaw('1 = 0');
    }

    private function payload(array $validated, Admin $admin, int $status, bool $isUpdate = false): array
    {
        $payload = [];
        $fields = ['contract_code', 'room_id', 'start_date', 'end_date', 'actual_end_date', 'billing_cycle_day', 'room_price', 'deposit_amount', 'note', 'parent_contract_id', 'renew_from_contract_id'];

        foreach ($fields as $field) {
            if (array_key_exists($field, $validated)) {
                $payload[$field] = in_array($field, ['room_price', 'deposit_amount'], true)
                    ? $this->normalizeDecimal($validated[$field])
                    : $validated[$field];
            }
        }

        if (! $isUpdate) {
            $payload['created_by'] = $admin->id;
            $payload['status'] = $status;
        }

        return $payload;
    }

    private function paginatedResource(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => ContractResource::collection($paginator->items())->resolve(),
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

    private function normalizedTenantPayloads(array $validated, ?Contract $contract): array
    {
        $isCreate = $contract === null;
        return collect($validated['tenants'] ?? [])
            ->map(fn (array $tenant): array => [
                'tenant_id' => (int) $tenant['tenant_id'],
                'join_date' => $tenant['join_date'],
                'leave_date' => $isCreate ? null : ($tenant['leave_date'] ?? null),
                'billing_start_date' => $tenant['billing_start_date'] ?? $tenant['join_date'],
                'billing_end_date' => $isCreate ? null : ($tenant['billing_end_date'] ?? ($tenant['leave_date'] ?? null)),
                'is_staying' => $isCreate ? true : (array_key_exists('is_staying', $tenant) ? filter_var($tenant['is_staying'], FILTER_VALIDATE_BOOLEAN) : blank($tenant['leave_date'] ?? null)),
            ])
            ->values()
            ->all();
    }

    private function normalizedVehiclePayloads(array $validated, bool $isCreate = false): array
    {
        return collect($validated['vehicles'] ?? [])
            ->map(fn (array $vehicle): array => [
                'vehicle_id' => (int) $vehicle['vehicle_id'],
                'started_at' => $vehicle['started_at'],
                'ended_at' => $isCreate ? null : ($vehicle['ended_at'] ?? null),
                'billing_start_date' => $vehicle['billing_start_date'] ?? $vehicle['started_at'],
                'billing_end_date' => $isCreate ? null : ($vehicle['billing_end_date'] ?? ($vehicle['ended_at'] ?? null)),
                'monthly_fee' => $this->normalizeDecimal((int) $vehicle['charge_policy'] === ContractVehicle::CHARGE_POLICY_FREE ? '0' : ($vehicle['monthly_fee'] ?? '0')),
                'charge_policy' => (int) $vehicle['charge_policy'],
                'is_active' => $isCreate ? true : (array_key_exists('is_active', $vehicle) ? filter_var($vehicle['is_active'], FILTER_VALIDATE_BOOLEAN) : blank($vehicle['ended_at'] ?? null)),
            ])
            ->values()
            ->all();
    }

    private function currentTenantPayloads(Contract $contract): array
    {
        return $contract->contractTenants()
            ->select(['tenant_id', 'join_date', 'leave_date', 'billing_start_date', 'billing_end_date', 'is_staying'])
            ->get()
            ->map(fn (ContractTenant $tenant): array => [
                'tenant_id' => (int) $tenant->tenant_id,
                'join_date' => $tenant->join_date?->toDateString(),
                'leave_date' => $tenant->leave_date?->toDateString(),
                'billing_start_date' => $tenant->billing_start_date?->toDateString(),
                'billing_end_date' => $tenant->billing_end_date?->toDateString(),
                'is_staying' => (bool) $tenant->is_staying,
            ])
            ->all();
    }

    private function currentVehiclePayloads(Contract $contract): array
    {
        return $contract->contractVehicles()
            ->select(['vehicle_id', 'started_at', 'ended_at', 'billing_start_date', 'billing_end_date', 'monthly_fee', 'charge_policy', 'is_active'])
            ->get()
            ->map(fn (ContractVehicle $vehicle): array => [
                'vehicle_id' => (int) $vehicle->vehicle_id,
                'started_at' => $vehicle->started_at?->toDateString(),
                'ended_at' => $vehicle->ended_at?->toDateString(),
                'billing_start_date' => $vehicle->billing_start_date?->toDateString(),
                'billing_end_date' => $vehicle->billing_end_date?->toDateString(),
                'monthly_fee' => (string) $vehicle->monthly_fee,
                'charge_policy' => (int) $vehicle->charge_policy,
                'is_active' => (bool) $vehicle->is_active,
            ])
            ->all();
    }

    private function assertContractDates(Contract $contract, array $payload): void
    {
        $startDate = $payload['start_date'] ?? $contract->start_date?->toDateString();
        $endDate = $payload['end_date'] ?? $contract->end_date?->toDateString();
        $actualEndDate = array_key_exists('actual_end_date', $payload) ? $payload['actual_end_date'] : $contract->actual_end_date?->toDateString();

        if (filled($startDate) && filled($endDate) && $endDate < $startDate) {
            $this->throwResponse('Ngày kết thúc hợp đồng phải lớn hơn hoặc bằng ngày bắt đầu.', 422);
        }

        if (filled($startDate) && filled($actualEndDate) && $actualEndDate < $startDate) {
            $this->throwResponse('Ngày kết thúc thực tế phải lớn hơn hoặc bằng ngày bắt đầu hợp đồng.', 422);
        }
    }



    private function assertTenantPayloads(Admin $admin, array $tenantPayloads, Room $room, ?int $contractId, int $status): void
    {
        $tenantIds = collect($tenantPayloads)->pluck('tenant_id')->all();

        foreach ($tenantPayloads as $tenantPayload) {
            if (filled($tenantPayload['leave_date']) && $tenantPayload['leave_date'] < $tenantPayload['join_date']) {
                $this->throwResponse('Ngày rời đi của khách thuê phải lớn hơn hoặc bằng ngày vào ở.', 422);
            }

            if (filled($tenantPayload['billing_end_date']) && filled($tenantPayload['billing_start_date']) && $tenantPayload['billing_end_date'] < $tenantPayload['billing_start_date']) {
                $this->throwResponse('Ngày kết thúc tính tiền của khách thuê phải lớn hơn hoặc bằng ngày bắt đầu tính tiền.', 422);
            }
        }

        $accessibleCount = AdminScope::applyTenantScope(Tenant::query(), $admin)
            ->whereIn('id', $tenantIds)
            ->count();

        if ($accessibleCount !== count($tenantIds)) {
            $this->throwResponse('Bạn không có quyền thêm một hoặc nhiều khách thuê vào hợp đồng này.', 403);
        }

        $inactiveTenantsCount = Tenant::query()
            ->whereIn('id', $tenantIds)
            ->where('status', '<>', Tenant::STATUS_RENTING)
            ->count();

        if ($inactiveTenantsCount > 0) {
            $this->throwResponse('Không thể lập hoặc cập nhật hợp đồng cho khách thuê ở trạng thái ngừng thuê hoặc không hoạt động.', 422);
        }

        if ($status === Contract::STATUS_ACTIVE) {
            $stayingTenantIds = collect($tenantPayloads)
                ->filter(fn (array $tenant): bool => (bool) $tenant['is_staying'])
                ->pluck('tenant_id')
                ->all();

            if ($stayingTenantIds !== [] && $this->hasActiveTenantConflict($stayingTenantIds, $contractId)) {
                $this->throwResponse('Có khách thuê đang ở trong hợp đồng hiệu lực khác, vui lòng kiểm tra lại.', 422);
            }
        }

        $invalidBuildingTenant = Tenant::query()
            ->whereIn('id', $tenantIds)
            ->whereNotNull('building_id')
            ->where('building_id', '<>', $room->building_id)
            ->whereDoesntHave('contractTenants.contract', fn (Builder $query): Builder => $query->where('room_id', $room->id))
            ->exists();

        if ($invalidBuildingTenant) {
            $this->throwResponse('Khách thuê trong hợp đồng phải thuộc tòa nhà/phòng đang thao tác hoặc chưa được gán tòa nhà.', 422);
        }
    }

    private function assertVehiclePayloads(Admin $admin, array $vehiclePayloads, array $tenantIds, ?int $contractId, int $status): void
    {
        if ($vehiclePayloads === []) {
            return;
        }

        foreach ($vehiclePayloads as $vehiclePayload) {
            if (filled($vehiclePayload['ended_at']) && $vehiclePayload['ended_at'] < $vehiclePayload['started_at']) {
                $this->throwResponse('Ngày kết thúc gửi xe phải lớn hơn hoặc bằng ngày bắt đầu gửi xe.', 422);
            }

            if (filled($vehiclePayload['billing_end_date']) && filled($vehiclePayload['billing_start_date']) && $vehiclePayload['billing_end_date'] < $vehiclePayload['billing_start_date']) {
                $this->throwResponse('Ngày kết thúc tính phí xe phải lớn hơn hoặc bằng ngày bắt đầu tính phí.', 422);
            }
        }

        $vehicleIds = collect($vehiclePayloads)->pluck('vehicle_id')->all();
        $vehicles = Vehicle::query()
            ->with('tenant:id,full_name')
            ->whereIn('id', $vehicleIds)
            ->whereHas('tenant', fn (Builder $tenantQuery): Builder => AdminScope::applyTenantScope($tenantQuery, $admin))
            ->get();

        if ($vehicles->count() !== count($vehicleIds)) {
            $this->throwResponse('Bạn không có quyền thêm một hoặc nhiều phương tiện vào hợp đồng này.', 403);
        }

        $invalidOwner = $vehicles->contains(fn (Vehicle $vehicle): bool => ! in_array((int) $vehicle->tenant_id, $tenantIds, true));

        if ($invalidOwner) {
            $this->throwResponse('Phương tiện trong hợp đồng phải thuộc một khách thuê đang có trong hợp đồng.', 422);
        }

        $inactiveVehicle = $vehicles->contains(fn (Vehicle $vehicle): bool => ! (bool) $vehicle->is_active);

        if ($inactiveVehicle) {
            $this->throwResponse('Không thể thêm phương tiện đã ngừng sử dụng vào hợp đồng.', 422);
        }

        if ($status === Contract::STATUS_ACTIVE) {
            $activeVehicleIds = collect($vehiclePayloads)
                ->filter(fn (array $vehicle): bool => (bool) $vehicle['is_active'])
                ->pluck('vehicle_id')
                ->all();

            if ($activeVehicleIds !== [] && $this->hasActiveVehicleConflict($activeVehicleIds, $contractId)) {
                $this->throwResponse('Có phương tiện đang được tính phí trong hợp đồng hiệu lực khác, vui lòng kiểm tra lại.', 422);
            }
        }
    }

    private function assertRoomCanBeUsed(Admin $admin, Room $room): void
    {
        if (! AdminScope::ensureBuildingAccess($admin, (int) $room->building_id)) {
            $this->throwResponse('Bạn không có quyền thao tác hợp đồng của tòa nhà này.', 403);
        }

        if ((int) $room->status !== Room::STATUS_ACTIVE) {
            $this->throwResponse('Chỉ có thể lập hợp đồng cho phòng đang hoạt động.', 422);
        }
    }

    private function assertRoomContractAvailability(int $roomId, ?int $ignoreContractId, int $status): void
    {
        if ($status !== Contract::STATUS_ACTIVE) {
            return;
        }

        $room = Room::find($roomId);
        if (! $room) {
            $this->throwResponse('Không tìm thấy phòng.', 404);
        }

        $hasActiveContract = Contract::query()
            ->where('room_id', $roomId)
            ->where('status', Contract::STATUS_ACTIVE)
            ->when($ignoreContractId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreContractId))
            ->exists();

        if ($hasActiveContract) {
            $this->throwResponse('Phòng này đã có hợp đồng đang hiệu lực, không thể tạo thêm hợp đồng mới.', 422);
        }
    }

    private function assertRoomCapacity(Room $room, array $tenantPayloads, ?int $ignoreContractId = null): void
    {
        $stayingCount = collect($tenantPayloads)
            ->filter(fn (array $tenant): bool => (bool) $tenant['is_staying'])
            ->count();

        if ($stayingCount < 1) {
            $this->throwResponse('Hợp đồng phải có ít nhất một khách thuê đang ở.', 422);
        }

        $otherOccupants = ContractTenant::query()
            ->where('is_staying', true)
            ->whereNull('leave_date')
            ->whereHas('contract', function (Builder $query) use ($room, $ignoreContractId): Builder {
                return $query->where('room_id', $room->id)
                    ->where('status', Contract::STATUS_ACTIVE)
                    ->when($ignoreContractId !== null, fn (Builder $q) => $q->whereKeyNot($ignoreContractId));
            })
            ->distinct('tenant_id')
            ->count('tenant_id');

        $totalOccupants = $otherOccupants + $stayingCount;

        if ((int) $room->max_occupants > 0 && $totalOccupants > (int) $room->max_occupants) {
            $this->throwResponse('Số khách thuê đang ở vượt quá sức chứa tối đa của phòng.', 422);
        }
    }

    private function assertDepositTransactions(array $transactions, int $currentBalanceCents, int $depositLimitCents): void
    {
        $balance = $currentBalanceCents;

        foreach ($transactions as $transaction) {
            $amount = $this->decimalToCents($transaction['amount'] ?? '0');

            if ($amount <= 0) {
                $this->throwResponse('Số tiền giao dịch cọc phải lớn hơn 0.', 422);
            }

            $type = (int) ($transaction['transaction_type'] ?? 0);

            if (in_array($type, [ContractDepositTransaction::TRANSACTION_TYPE_COLLECT, ContractDepositTransaction::TRANSACTION_TYPE_TRANSFER], true)) {
                $balance += $amount;
            }

            if (in_array($type, [ContractDepositTransaction::TRANSACTION_TYPE_REFUND, ContractDepositTransaction::TRANSACTION_TYPE_DEDUCT], true)) {
                if ($amount > $balance) {
                    $this->throwResponse('Số tiền hoàn/khấu trừ cọc không được vượt quá số dư cọc hiện tại.', 422);
                }

                $balance -= $amount;
            }
        }

        if ($depositLimitCents >= 0 && $balance > $depositLimitCents) {
            $this->throwResponse('Tổng số dư cọc không được vượt quá tiền cọc trong hợp đồng.', 422);
        }
    }

    private function syncContractTenants(Contract $contract, array $tenantPayloads, Admin $admin, bool $isCreate): void
    {
        $incomingTenantIds = collect($tenantPayloads)->pluck('tenant_id')->all();
        $existingTenants = $contract->contractTenants()->lockForUpdate()->get()->keyBy('tenant_id');

        foreach ($tenantPayloads as $payload) {
            $contractTenant = $existingTenants->get($payload['tenant_id']);
            $payload['created_by'] = $contractTenant?->created_by ?? $admin->id;

            if ($contractTenant) {
                $contractTenant->fill($payload)->save();

                continue;
            }

            $contract->contractTenants()->create($payload);
        }

        $missingTenantRows = $contract->contractTenants()->whereNotIn('tenant_id', $incomingTenantIds)->lockForUpdate()->get();

        if ($isCreate) {
            $missingTenantRows->each(fn (ContractTenant $contractTenant): ?bool => $contractTenant->delete());

            return;
        }

        $today = now()->toDateString();
        $missingTenantRows->each(fn (ContractTenant $contractTenant): bool => $contractTenant->forceFill([
            'leave_date' => $contractTenant->leave_date?->toDateString() ?? $today,
            'billing_end_date' => $contractTenant->billing_end_date?->toDateString() ?? $today,
            'is_staying' => false,
        ])->save());
    }

    private function syncContractVehicles(Contract $contract, array $vehiclePayloads, bool $isCreate): void
    {
        $incomingVehicleIds = collect($vehiclePayloads)->pluck('vehicle_id')->all();
        $existingVehicles = $contract->contractVehicles()->lockForUpdate()->get()->keyBy('vehicle_id');

        foreach ($vehiclePayloads as $payload) {
            $contractVehicle = $existingVehicles->get($payload['vehicle_id']);

            if ($contractVehicle) {
                $contractVehicle->fill($payload)->save();

                continue;
            }

            $contract->contractVehicles()->create($payload);
        }

        $missingVehicleRows = $contract->contractVehicles()->whereNotIn('vehicle_id', $incomingVehicleIds)->lockForUpdate()->get();

        if ($isCreate) {
            $missingVehicleRows->each(fn (ContractVehicle $contractVehicle): ?bool => $contractVehicle->delete());

            return;
        }

        $today = now()->toDateString();
        $missingVehicleRows->each(fn (ContractVehicle $contractVehicle): bool => $contractVehicle->forceFill([
            'ended_at' => $contractVehicle->ended_at?->toDateString() ?? $today,
            'billing_end_date' => $contractVehicle->billing_end_date?->toDateString() ?? $today,
            'is_active' => false,
        ])->save());
    }

    private function appendDepositTransactions(Contract $contract, array $transactions, Admin $admin): void
    {
        foreach ($transactions as $transaction) {
            $contract->depositTransactions()->create([
                'transaction_type' => (int) $transaction['transaction_type'],
                'amount' => $this->normalizeDecimal($transaction['amount']),
                'transaction_date' => $transaction['transaction_date'],
                'payment_method' => (int) $transaction['payment_method'],
                'note' => $transaction['note'] ?? null,
                'created_by' => $admin->id,
            ]);
        }
    }

    private function closeActiveContractRows(Contract $contract, string $endDate): void
    {
        $contract->contractTenants()
            ->where('is_staying', true)
            ->lockForUpdate()
            ->get()
            ->each(fn (ContractTenant $contractTenant): bool => $contractTenant->forceFill([
                'leave_date' => $contractTenant->leave_date?->toDateString() ?? $endDate,
                'billing_end_date' => $contractTenant->billing_end_date?->toDateString() ?? $endDate,
                'is_staying' => false,
            ])->save());

        $contract->contractVehicles()
            ->where('is_active', true)
            ->lockForUpdate()
            ->get()
            ->each(fn (ContractVehicle $contractVehicle): bool => $contractVehicle->forceFill([
                'ended_at' => $contractVehicle->ended_at?->toDateString() ?? $endDate,
                'billing_end_date' => $contractVehicle->billing_end_date?->toDateString() ?? $endDate,
                'is_active' => false,
            ])->save());
    }

    private function refreshRoomOccupants(int $roomId): void
    {
        $occupants = ContractTenant::query()
            ->where('is_staying', true)
            ->whereNull('leave_date')
            ->whereHas('contract', fn (Builder $query): Builder => $query->where('room_id', $roomId)->where('status', Contract::STATUS_ACTIVE))
            ->distinct('tenant_id')
            ->count('tenant_id');

        Room::query()->whereKey($roomId)->update(['current_occupants' => $occupants]);
    }

    private function hasActiveTenantConflict(array $tenantIds, ?int $ignoreContractId): bool
    {
        return ContractTenant::query()
            ->whereIn('tenant_id', $tenantIds)
            ->where('is_staying', true)
            ->whereNull('leave_date')
            ->whereHas('contract', function (Builder $query) use ($ignoreContractId): void {
                $query->where('status', Contract::STATUS_ACTIVE)
                    ->when($ignoreContractId !== null, fn (Builder $contractQuery): Builder => $contractQuery->whereKeyNot($ignoreContractId));
            })
            ->exists();
    }

    private function hasActiveVehicleConflict(array $vehicleIds, ?int $ignoreContractId): bool
    {
        return ContractVehicle::query()
            ->whereIn('vehicle_id', $vehicleIds)
            ->where('is_active', true)
            ->whereHas('contract', function (Builder $query) use ($ignoreContractId): void {
                $query->where('status', Contract::STATUS_ACTIVE)
                    ->when($ignoreContractId !== null, fn (Builder $contractQuery): Builder => $contractQuery->whereKeyNot($ignoreContractId));
            })
            ->exists();
    }

    private function depositBalanceCents(Contract $contract): int
    {
        return $contract->depositTransactions()
            ->select(['transaction_type', 'amount'])
            ->get()
            ->reduce(function (int $balance, ContractDepositTransaction $transaction): int {
                $amount = $this->decimalToCents($transaction->amount);

                if (in_array((int) $transaction->transaction_type, [ContractDepositTransaction::TRANSACTION_TYPE_COLLECT, ContractDepositTransaction::TRANSACTION_TYPE_TRANSFER], true)) {
                    return $balance + $amount;
                }

                return $balance - $amount;
            }, 0);
    }

    private function assertStatusTransition(int $currentStatus, int $nextStatus): void
    {
        if ($currentStatus === $nextStatus) {
            return;
        }

        $allowedTransitions = [
            Contract::STATUS_ACTIVE => [Contract::STATUS_EXPIRED, Contract::STATUS_LIQUIDATED, Contract::STATUS_CANCELLED],
            Contract::STATUS_EXPIRED => [Contract::STATUS_LIQUIDATED],
            Contract::STATUS_LIQUIDATED => [],
            Contract::STATUS_CANCELLED => [],
        ];

        if (! in_array($nextStatus, $allowedTransitions[$currentStatus] ?? [], true)) {
            $currentLabel = Contract::STATUS_LABELS[$currentStatus] ?? 'không xác định';
            $nextLabel = Contract::STATUS_LABELS[$nextStatus] ?? 'không xác định';

            $this->throwResponse("Không thể chuyển hợp đồng từ trạng thái {$currentLabel} sang {$nextLabel}.", 422);
        }
    }

    private function storeContractFiles(Request $request, Contract $contract, array &$uploadedPaths): array
    {
        return collect($request->file('contract_files', []))
            ->filter(fn ($file): bool => $file instanceof UploadedFile)
            ->map(function (UploadedFile $file) use ($contract, &$uploadedPaths): string {
                $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'file');
                $baseName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'hop-dong';
                $fileName = now()->format('YmdHis').'_'.$baseName.'_'.Str::random(12).'.'.$extension;
                $path = 'contracts/'.$contract->id.'/files/'.$fileName;
                $stored = Storage::disk(self::FILE_DISK)->putFileAs('contracts/'.$contract->id.'/files', $file, $fileName, ['visibility' => 'public']);

                if (! $stored) {
                    $this->throwResponse('Không thể lưu file hợp đồng, vui lòng thử lại.', 500);
                }

                $uploadedPaths[] = $path;

                return $path;
            })
            ->values()
            ->all();
    }

    private function contractFilesToDelete(Contract $contract, array $deletePaths): array
    {
        $currentFiles = collect($contract->contract_files ?? [])->filter()->values();
        $deletePaths = collect($deletePaths)->filter()->values();

        return $currentFiles
            ->filter(fn (string $path): bool => $deletePaths->contains($path))
            ->values()
            ->all();
    }

    private function deleteDiskFiles(array $paths): void
    {
        collect($paths)
            ->filter()
            ->unique()
            ->each(fn (string $path): bool => ImageHelper::deleteFromDisk($path, self::FILE_DISK));
    }

    private function ensureRoomAccess(Admin $admin, int $roomId): bool
    {
        $room = Room::query()->select(['id', 'building_id'])->find($roomId);

        return $room ? AdminScope::ensureBuildingAccess($admin, (int) $room->building_id) : false;
    }

    private function ensureTenantAccess(Admin $admin, int $tenantId): bool
    {
        return AdminScope::applyTenantScope(Tenant::query(), $admin)->whereKey($tenantId)->exists();
    }

    private function canManageContracts(Admin $admin): bool
    {
        return AdminScope::isSuperAdmin($admin) || AdminScope::isBuildingManager($admin);
    }

    private function isTerminalContract(Contract $contract): bool
    {
        return in_array((int) $contract->status, [Contract::STATUS_LIQUIDATED, Contract::STATUS_CANCELLED], true);
    }

    private function hasStructuralUpdate(array $validated): bool
    {
        return collect(array_keys($validated))->contains(fn (string $key): bool => ! in_array($key, ['note', 'contract_files', 'delete_contract_files'], true));
    }

    private function hasDeleteBlockingData(Contract $contract): bool
    {
        return (int) $contract->deposit_transactions_count > 0
            || (int) $contract->room_movements_count > 0
            || (int) $contract->invoices_count > 0;
    }

    private function throwResponse(string $message, int $statusCode): never
    {
        throw new HttpResponseException(ApiResponse::responseJson(false, $message, $statusCode, null, $statusCode));
    }

    private function normalizeDecimal(mixed $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '0.00';
        }

        [$integer, $decimal] = array_pad(explode('.', $value, 2), 2, '0');
        $integer = ltrim($integer, '0') ?: '0';
        $decimal = str_pad(substr($decimal, 0, 2), 2, '0');

        return $integer.'.'.$decimal;
    }

    private function decimalToCents(mixed $value): int
    {
        [$integer, $decimal] = explode('.', $this->normalizeDecimal($value));

        return ((int) $integer * 100) + (int) str_pad($decimal, 2, '0');
    }

    private function loadDetailRelations(Contract $contract): void
    {
        $contract->load($this->detailRelations());
        $contract->loadCount($this->detailCounts());
    }

    private function listColumns(): array
    {
        return ['id', 'contract_code', 'room_id', 'start_date', 'end_date', 'actual_end_date', 'billing_cycle_day', 'room_price', 'deposit_amount', 'status', 'created_by', 'created_at', 'updated_at'];
    }

    private function detailColumns(): array
    {
        return ['id', 'contract_code', 'room_id', 'start_date', 'end_date', 'actual_end_date', 'billing_cycle_day', 'room_price', 'deposit_amount', 'status', 'contract_files', 'note', 'created_by', 'created_at', 'updated_at'];
    }

    private function listRelations(): array
    {
        return [
            'room:id,building_id,room_number,slug,status,max_occupants,current_occupants',
            'room.building:id,name,slug,manager_admin_id,status',
            'creator:id,username,full_name,email,role,status',
            'contractTenants' => fn ($query) => $query->select(['id', 'contract_id', 'tenant_id', 'join_date', 'leave_date', 'billing_start_date', 'billing_end_date', 'is_staying'])->orderBy('join_date'),
            'contractTenants.tenant:id,full_name,phone,email,identity_number',
        ];
    }

    private function detailRelations(): array
    {
        return [
            'room:id,building_id,room_type_id,room_number,slug,floor,area_m2,base_price,max_occupants,current_occupants,status,description,created_by,created_at,updated_at',
            'room.building:id,name,slug,manager_admin_id,status,address',
            'room.roomType:id,name,slug,status',
            'creator:id,username,full_name,email,phone,role,status',
            'contractTenants' => fn ($query) => $query->select(['id', 'contract_id', 'tenant_id', 'join_date', 'leave_date', 'billing_start_date', 'billing_end_date', 'is_staying', 'created_by', 'created_at', 'updated_at'])->orderBy('join_date')->orderBy('id'),
            'contractTenants.tenant:id,full_name,phone,email,identity_number,status,building_id',
            'contractVehicles' => fn ($query) => $query->select(['id', 'contract_id', 'vehicle_id', 'started_at', 'ended_at', 'billing_start_date', 'billing_end_date', 'monthly_fee', 'charge_policy', 'is_active', 'created_at', 'updated_at'])->orderByDesc('is_active')->orderBy('started_at')->orderBy('id'),
            'contractVehicles.vehicle:id,tenant_id,vehicle_type,license_plate,brand,color,is_active',
            'contractVehicles.vehicle.tenant:id,full_name,phone,email',
            'depositTransactions' => fn ($query) => $query->select(['id', 'contract_id', 'transaction_type', 'amount', 'transaction_date', 'payment_method', 'note', 'created_by', 'created_at'])->orderByDesc('transaction_date')->orderByDesc('id'),
            'depositTransactions.creator:id,full_name,username,email',
        ];
    }

    private function listCounts(): array
    {
        return ['contractTenants', 'tenants', 'vehicles', 'depositTransactions'];
    }

    private function detailCounts(): array
    {
        return ['contractTenants', 'tenants', 'vehicles', 'contractVehicles', 'depositTransactions', 'roomMovements'];
    }

    private function deleteBlockingCounts(): array
    {
        return ['depositTransactions', 'roomMovements', 'invoices'];
    }

    private function notifyTenantsOfNewContract(Contract $contract, $adminId): void
    {
        try {
            $contract->loadMissing(['room.building', 'tenants']);
            foreach ($contract->tenants as $tenant) {
                $tenantNotification = \App\Models\Notification::create([
                    'title' => 'Hợp đồng mới được tạo',
                    'content' => "Hợp đồng {$contract->contract_code} của bạn tại phòng " . ($contract->room?->room_number ?? 'không rõ') . " đã được tạo thành công.",
                    'notification_type' => \App\Models\Notification::NOTIFICATION_TYPE_SYSTEM,
                    'target_type' => \App\Models\Notification::TARGET_TYPE_TENANT,
                    'building_id' => $contract->room?->building_id,
                    'room_id' => $contract->room_id,
                    'tenant_id' => $tenant->id,
                    'published_at' => now(),
                    'status' => \App\Models\Notification::STATUS_SENT,
                    'created_by' => $adminId,
                ]);

                // Broadcast real-time notification to the tenant
                broadcast(new \App\Events\NotificationSent($tenantNotification));
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error notifying tenants of new contract: ' . $e->getMessage());
        }
    }
}

