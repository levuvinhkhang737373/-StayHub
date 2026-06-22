<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AdminActivityLogger;
use App\Helpers\AdminScope;
use App\Helpers\ApiResponse;
use App\Helpers\ImageHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Room\RoomRequest;
use App\Http\Requests\Admin\Room\TranferSingleTenantRequest;
use App\Models\Admin;
use App\Models\AssetTemplate;
use App\Models\Building;
use App\Models\Contract;
use App\Models\ContractDepositTransaction;
use App\Models\ContractTenant;
use App\Models\ContractVehicle;
use App\Models\MeterDevice;
use App\Models\MeterReading;
use App\Models\Notification;
use App\Models\Room;
use App\Models\RoomAsset;
use App\Models\RoomMovement;
use App\Models\Service;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoomController extends Controller
{
    private const ROOM_STATUS_ACTIVE = 1;     // TODO: xác nhận lại theo enum RoomStatus thật
    private const CONTRACT_STATUS_ACTIVE = 1; // Khớp dữ liệu mẫu
    private const CONTRACT_STATUS_ENDED = 2;  // Khớp dữ liệu mẫu
    const DEPOSIT_COLLECT      = 1;
    const DEPOSIT_REFUND       = 2;
    const DEPOSIT_DEDUCTION    = 3;
    const DEPOSIT_TRANSFER_OUT = 4;
    const DEPOSIT_TRANSFER_IN  = 5;
    const MOVEMENT_TRANSFER = 1;
    const MOVEMENT_MERGE    = 2;

    /**
     * CHỈ dùng để hiển thị text cho người dùng (vd trang lịch sử chuyển phòng).
     * KHÔNG được gọi hàm này khi ghi giá trị xuống DB - các cột movement_type/
     * transaction_type là tinyint, phải ghi đúng số nguyên (self::MOVEMENT_*,
     * self::DEPOSIT_*), ghi chuỗi label vào sẽ lỗi insert hoặc bị ép sai giá trị.
     */
    protected function getMovementLabel(int $type): string
    {
        return match ($type) {
            self::MOVEMENT_TRANSFER => 'Chuyển phòng (tạo hợp đồng mới)',
            self::MOVEMENT_MERGE    => 'Ghép vào hợp đồng đang ở',
            default => 'Không xác định',
        };
    }

    /** CHỈ dùng để hiển thị - xem ghi chú ở getMovementLabel(). */
    protected function getDepositLabel(int $type): string
    {
        return match ($type) {
            self::DEPOSIT_COLLECT      => 'Thu cọc',
            self::DEPOSIT_REFUND       => 'Hoàn cọc',
            self::DEPOSIT_DEDUCTION    => 'Trừ cọc',
            self::DEPOSIT_TRANSFER_OUT => 'Chuyển cọc ra (hợp đồng cũ)',
            self::DEPOSIT_TRANSFER_IN  => 'Nhận cọc chuyển vào (hợp đồng mới)',
            default => 'Không xác định',
        };
    }

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

            AdminActivityLogger::write($admin, 'create_room', Room::class, $room->id, null, $room->fresh()->toArray(), $request);

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

            AdminActivityLogger::write($admin, 'update_room', Room::class, $room->id, $oldData, $room->fresh()->toArray(), $request);

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
            AdminActivityLogger::write($admin, 'delete_room', Room::class, (int) $id, $oldData, null, $request);
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
            AdminActivityLogger::write($admin, 'update_room_status', Room::class, $room->id, $oldData, $room->fresh()->toArray(), $request);
            return ApiResponse::responseJson(true, "Cập nhật trạng thái phòng thành công", 200, null, 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Lỗi server: ' . $e->getMessage(), 500, null, 500);
        }
    }

    public function transferTenant(TranferSingleTenantRequest $request)
    {
        DB::beginTransaction();
        try {
            $movementDate = $request->movement_date instanceof Carbon
                ? $request->movement_date
                : Carbon::parse($request->movement_date);

            $tenant = Tenant::find($request->tenant_id);
            if (!$tenant) {
                throw ValidationException::withMessages([
                    'tenant_id' => "Không tìm thấy khách thuê #{$request->tenant_id}.",
                ]);
            }

            $currentContractTenant = ContractTenant::where('tenant_id', $request->tenant_id)
                ->where('is_staying', 1)
                ->whereNull('leave_date')
                ->lockForUpdate()
                ->first();

            if (!$currentContractTenant) {
                throw ValidationException::withMessages([
                    'tenant_id' => "Khách thuê {$request->tenant_id} hiện không có hợp đồng đang ở.",
                ]);
            }

            $oldContract = Contract::lockForUpdate()->find($currentContractTenant->contract_id);
            if (!$oldContract) {
                throw ValidationException::withMessages([
                    'tenant_id' => 'Không tìm thấy hợp đồng cũ.',
                ]);
            }

            $oldRoomId = $oldContract->room_id;
            if ($oldRoomId == $request->to_room_id) {
                throw ValidationException::withMessages([
                    'to_room_id' => 'Phòng đích không được là phòng cũ.',
                ]);
            }

            // Khoá CẢ HAI phòng cùng lúc, sắp theo id tăng dần - 2 lượt chuyển phòng ngược
            // chiều xảy ra cùng lúc luôn xin khoá theo đúng 1 thứ tự, tránh deadlock.
            $rooms = Room::with('building')
                ->whereIn('id', [$oldRoomId, $request->to_room_id])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $oldRoom = $rooms->get($oldRoomId);
            $toRoom = $rooms->get($request->to_room_id);

            // assertCanTransfer() giờ THROW khi không hợp lệ, không còn return response bị
            // bỏ quên như bản trước - lỗi sẽ thực sự chặn được luồng xử lý phía dưới.
            $this->assertCanTransfer($tenant, $oldRoom, $toRoom);

            $finalReadings = $this->closeOldRoomMeters($oldRoom, $movementDate, $request->closing_meter_readings ?? [], $request->actor_admin_id ?? $request->user()->id);

            $currentContractTenant->update([
                'leave_date' => $movementDate->toDateString(),
                'billing_end_date' => $movementDate->toDateString(),
                'is_staying' => 0,
            ]);

            $remainingInOldContract = ContractTenant::where('contract_id', $oldContract->id)
                ->where('is_staying', 1)
                ->exists();

            if (!$remainingInOldContract) {
                $oldContract->update([
                    'status' => self::CONTRACT_STATUS_ENDED,
                    'actual_end_date' => $movementDate->toDateString(),
                ]);
            }

            // Dùng thẳng hằng số (int) - KHÔNG gọi getDepositLabel()/getMovementLabel() ở đây,
            // 2 hàm đó chỉ trả về chuỗi text để hiển thị, ghi chuỗi vào cột tinyint sẽ sai.
            $movementType = self::MOVEMENT_TRANSFER;

            $destinationContract = Contract::where('room_id', $toRoom->id)
                ->where('status', self::CONTRACT_STATUS_ACTIVE)
                ->whereNull('actual_end_date')
                ->lockForUpdate()
                ->first();

            if ($destinationContract) {
                $movementType = self::MOVEMENT_MERGE;
            } else {
                $destinationContract = Contract::create([
                    'contract_code' => $this->generateContractCode($toRoom),
                    'room_id' => $toRoom->id,
                    'start_date' => $movementDate->toDateString(),
                    'end_date' => $oldContract->end_date, // giữ nguyên hạn hợp đồng cũ - chỉnh lại nếu nghiệp vụ của bạn khác
                    'billing_cycle_day' => $oldContract->billing_cycle_day,
                    'room_price' => $toRoom->base_price,
                    'deposit_amount' => 0, // sẽ được cộng dần ở bước handleDeposit() bên dưới
                    'status' => self::CONTRACT_STATUS_ACTIVE,
                    'payment_status' => 2, // dữ liệu mẫu toàn bộ hợp đồng đều dùng giá trị 2, dùng tạm làm baseline
                    'note' => $request->note,
                    'created_by' => $request->actor_admin_id ?? $request->user()->id,
                    'parent_contract_id' => $oldContract->id,
                ]);
            }

            // --- Từ đây trở xuống PHẢI chạy cho CẢ 2 trường hợp (tạo mới lẫn ghép) ---
            // Bản trước đặt nhầm toàn bộ khối này vào trong nhánh "else", khiến case ghép
            // phòng (phổ biến nhất) không thêm được tenant vào hợp đồng mới, không trừ sức
            // chứa phòng cũ, và không return response nào cả.
            ContractTenant::create([
                'contract_id' => $destinationContract->id,
                'tenant_id' => $request->tenant_id,
                'join_date' => $movementDate->toDateString(),
                'billing_start_date' => $movementDate->toDateString(),
                'is_staying' => true,
                'created_by' => $request->actor_admin_id ?? $request->user()->id,
            ]);

            // Chỉ thực hiện trừ nếu số lượng người lớn hơn 0
            if ($oldRoom->current_occupants > 0) {
                $oldRoom->decrement('current_occupants');
            } else {
                // Nếu vốn dĩ đã bằng 0 thì ép hẳn về 0 (hoặc giữ nguyên) để đảm bảo không bị âm trong DB
                $oldRoom->forceFill(['current_occupants' => 0])->save();
            }

            $toRoom->increment('current_occupants');
            // Lưu ý: KHÔNG tự đổi rooms.status theo sức chứa - dữ liệu mẫu cho thấy status
            // không đi theo current_occupants, nhiều khả năng là cờ bảo trì/hoạt động riêng.

            $this->handleDeposit(
                $oldContract,
                $destinationContract,
                (float) ($request->deposit_settlement_amount ?? 0),
                (float) ($request->deposit_deduction_amount ?? 0),
                (float) ($request->deposit_refund_amount ?? 0),
                $movementDate,
                $request->actor_admin_id ?? $request->user()->id,
            );

            $this->ensureNewRoomMeters($toRoom, $request->new_room_opening_readings ?? [], $movementDate);

            // Tên field gốc "carry_vehicleIds" không khớp convention snake_case của các field
            // khác - đổi thành carry_vehicle_ids. XÁC NHẬN LẠI tên field thật trong
            // TranferSingleTenantRequest của bạn; ?? [] để không bao giờ truyền null vào
            // carryOverVehicles() (type-hint array không nullable, truyền null sẽ TypeError).
            $this->carryOverVehicles($oldContract, $destinationContract, $request->carry_vehicle_ids ?? [], $movementDate);

            $roomMovement = RoomMovement::create([
                'tenant_id' => $request->tenant_id,
                'contract_id' => $destinationContract->id,
                'from_room_id' => $oldRoom->id,
                'to_room_id' => $toRoom->id,
                'movement_type' => $movementType,
                'movement_date' => $movementDate->toDateTimeString(),
                'old_room_final_amount' => $request->deposit_settlement_amount ?? 0, // TODO: chỉnh lại nếu ý nghĩa cột này khác trong app của bạn
                'transfer_fee' => $request->transfer_fee ?? 0,
                'deposit_transfer_amount' => max(
                    (float) ($request->deposit_settlement_amount ?? 0)
                        - (float) ($request->deposit_deduction_amount ?? 0)
                        - (float) ($request->deposit_refund_amount ?? 0),
                    0
                ),
                'deposit_refund_amount' => $request->deposit_refund_amount ?? 0,
                'deduction_amount' => $request->deposit_deduction_amount ?? 0,
                'final_electric_reading' => $finalReadings['electric'],
                'final_water_reading' => $finalReadings['water'],
                'note' => $request->note,
                'created_by' => $request->actor_admin_id ?? $request->user()->id,
            ]);

            $this->writeAdminLog($request->actor_admin_id ?? $request->user()->id, $oldContract, $destinationContract, $oldRoom, $toRoom, $request);
            $this->notifyTenant($tenant, $toRoom, $movementDate);

            DB::commit();

            return ApiResponse::responseJson(true, 'Chuyển phòng thành công', 200, $roomMovement, 200);
        } catch (ValidationException $e) {
            DB::rollBack();
            $firstError = collect($e->errors())->flatten()->first() ?? $e->getMessage();
            return ApiResponse::responseJson(false, $firstError, 422, $e->errors(), 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::responseJson(false, 'Lỗi server: ' . $e->getMessage(), 500, null, 500);
        }
    }

    /**
     * THROW khi không hợp lệ - không được return response ở đây. Hàm protected/private gọi
     * từ trong transferTenant() không có khả năng tự ngắt request bằng return; chỉ throw
     * mới dừng được luồng xử lý và bị catch đúng chỗ.
     */
    protected function assertCanTransfer(Tenant $tenant, Room $oldRoom, Room $toRoom): void
    {
        if ($toRoom->status !== self::ROOM_STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'to_room_id' => 'Phòng đích đang không ở trạng thái cho thuê được.',
            ]);
        }

        if ($toRoom->current_occupants >= $toRoom->max_occupants) {
            throw ValidationException::withMessages([
                'to_room_id' => 'Phòng đích đã đầy, không thể chuyển vào.',
            ]);
        }

        $destinationBuilding = $toRoom->relationLoaded('building')
            ? $toRoom->building
            : $toRoom->building()->select(['id', 'gender_policy'])->first();

        if (! $destinationBuilding?->allowsTenantGender($tenant->gender)) {
            throw ValidationException::withMessages([
                'to_room_id' => 'Giới tính khách thuê không phù hợp với chính sách giới tính của tòa nhà.',
            ]);
        }
    }

    private function closeOldRoomMeters(Room $oldRoom, Carbon $movementDate, array $closingReadings, ?int $actorAdminId): array
    {
        $readingsByDeviceId = collect($closingReadings)->keyBy('meter_device_id');
        $result = ['electric' => null, 'water' => null];

        $devices = MeterDevice::where('room_id', $oldRoom->id)
            ->where('status', 1)
            ->with('service')
            ->get();

        foreach ($devices as $device) {
            $input = $readingsByDeviceId->get($device->id);
            if (!$input) {
                continue; // không có chỉ số chốt cho công tơ này (vd dịch vụ không theo chỉ số), bỏ qua
            }

            $currentReading = (float) $input['current_reading'];
            $year = $movementDate->year;
            $month = $movementDate->month;

            $previousReading = MeterReading::where('meter_device_id', $device->id)
                ->where(function ($q) use ($year, $month) {
                    $q->where('billing_year', '<', $year)
                        ->orWhere(function ($q2) use ($year, $month) {
                            $q2->where('billing_year', $year)->where('billing_month', '<', $month);
                        });
                })
                ->orderByDesc('billing_year')
                ->orderByDesc('billing_month')
                ->first()?->current_reading ?? $device->initial_reading;

            MeterReading::updateOrCreate(
                [
                    'meter_device_id' => $device->id,
                    'billing_year' => $year,
                    'billing_month' => $month,
                ],
                [
                    'previous_reading' => $previousReading,
                    'current_reading' => $currentReading,
                    'consumption' => max($currentReading - $previousReading, 0),
                    'reading_date' => $movementDate->toDateString(),
                    'status' => 3, // khớp dữ liệu mẫu: 3 = đã chốt/hoàn tất
                    'note' => 'Chỉ số chốt sổ khi tenant chuyển phòng.',
                    'created_by' => $actorAdminId,
                ]
            );

            $slug = $device->service?->slug;
            if ($slug === 'dien-sinh-hoat') {
                $result['electric'] = $currentReading;
            } elseif ($slug === 'nuoc-sinh-hoat') {
                $result['water'] = $currentReading;
            }
        }

        return $result;
    }

    private function ensureNewRoomMeters(Room $toRoom, array $openingReadings, Carbon $movementDate): void
    {
        $meteredServices = Service::where('charge_method', 1)->where('is_active', true)->get();

        foreach ($meteredServices as $service) {
            $exists = MeterDevice::where('room_id', $toRoom->id)
                ->where('service_id', $service->id)
                ->where('status', 1)
                ->exists();

            if ($exists) {
                continue;
            }

            $openingReading = $openingReadings[$service->id] ?? null;

            if ($openingReading === null) {
                // THROW, không return - bản trước return response ở đây chỉ thoát khỏi
                // chính hàm này, transferTenant() vẫn chạy tiếp như chưa có gì sai.
                throw ValidationException::withMessages([
                    'new_room_opening_readings' => "Phòng đích chưa có công tơ {$service->name}, cần nhập chỉ số khởi điểm.",
                ]);
            }

            MeterDevice::create([
                'room_id' => $toRoom->id,
                'service_id' => $service->id,
                'meter_type' => $this->resolveMeterType($service),
                'initial_reading' => $openingReading,
                'installed_at' => $movementDate->toDateString(),
                'status' => 1,
                'note' => 'Công tơ tạo khi tenant chuyển vào phòng.',
            ]);
        }
    }

    private function resolveMeterType(Service $service): int
    {
        // TODO: chỉnh lại nếu sau này có thêm dịch vụ tính theo chỉ số khác ngoài điện/nước.
        return match ($service->slug) {
            'dien-sinh-hoat' => 1,
            'nuoc-sinh-hoat' => 2,
            default => 1,
        };
    }

    private function carryOverVehicles(Contract $oldContract, Contract $destinationContract, array $vehicleIds, Carbon $movementDate): void
    {
        if (empty($vehicleIds)) {
            return;
        }

        $oldVehicleRows = ContractVehicle::where('contract_id', $oldContract->id)
            ->whereIn('vehicle_id', $vehicleIds)
            ->where('is_active', true)
            ->get();

        foreach ($oldVehicleRows as $row) {
            $row->update([
                'ended_at' => $movementDate->toDateString(),
                'billing_end_date' => $movementDate->toDateString(),
                'is_active' => false,
            ]);

            ContractVehicle::create([
                'contract_id' => $destinationContract->id,
                'vehicle_id' => $row->vehicle_id,
                'started_at' => $movementDate->toDateString(),
                'billing_start_date' => $movementDate->toDateString(),
                'monthly_fee' => $row->monthly_fee, // giữ nguyên phí cũ - chỉnh lại nếu bảng giá phòng mới khác
                'charge_policy' => $row->charge_policy,
                'is_active' => true,
            ]);
        }
    }

    private function handleDeposit(
        Contract $oldContract,
        Contract $destinationContract,
        float $settlementAmount,
        float $deductionAmount,
        float $refundAmount,
        Carbon $movementDate,
        ?int $actorAdminId,
    ): void {
        if ($deductionAmount > 0) {
            ContractDepositTransaction::create([
                'contract_id' => $oldContract->id,
                'transaction_type' => self::DEPOSIT_DEDUCTION, // số nguyên, KHÔNG gọi getDepositLabel()
                'amount' => $deductionAmount,
                'transaction_date' => $movementDate->toDateString(),
                'note' => 'Trừ cọc khi chuyển phòng.',
                'created_by' => $actorAdminId,
            ]);
        }

        if ($refundAmount > 0) {
            ContractDepositTransaction::create([
                'contract_id' => $oldContract->id,
                'transaction_type' => self::DEPOSIT_REFUND,
                'amount' => $refundAmount,
                'transaction_date' => $movementDate->toDateString(),
                'note' => 'Hoàn cọc khi chuyển phòng.',
                'created_by' => $actorAdminId,
            ]);
        }

        $transferAmount = max($settlementAmount - $deductionAmount - $refundAmount, 0);

        if ($transferAmount <= 0) {
            return;
        }

        ContractDepositTransaction::create([
            'contract_id' => $oldContract->id,
            'transaction_type' => self::DEPOSIT_TRANSFER_OUT,
            'amount' => $transferAmount,
            'transaction_date' => $movementDate->toDateString(),
            'note' => "Chuyển cọc sang hợp đồng #{$destinationContract->id}.",
            'created_by' => $actorAdminId,
        ]);

        ContractDepositTransaction::create([
            'contract_id' => $destinationContract->id,
            'transaction_type' => self::DEPOSIT_TRANSFER_IN,
            'amount' => $transferAmount,
            'transaction_date' => $movementDate->toDateString(),
            'note' => "Nhận cọc chuyển từ hợp đồng #{$oldContract->id}.",
            'created_by' => $actorAdminId,
        ]);

        // Cộng dần vào deposit_amount của hợp đồng đích - quan trọng khi nhiều tenant
        // cùng chuyển vào 1 hợp đồng mới (mỗi tenant transfer-in 1 lần, cộng dồn lại).
        $destinationContract->increment('deposit_amount', $transferAmount);
    }

    private function writeAdminLog(?int $actorAdminId, Contract $oldContract, Contract $destinationContract, Room $oldRoom, Room $toRoom, Request $request): void
    {
        if (!$actorAdminId) {
            return;
        }

        $actorAdmin = Admin::find($actorAdminId);

        if (! $actorAdmin) {
            return;
        }

        AdminActivityLogger::write(
            $actorAdmin,
            'transfer_room',
            Contract::class,
            $oldContract->id,
            ['room_id' => $oldRoom->id, 'status' => self::CONTRACT_STATUS_ACTIVE],
            $oldContract->fresh()->toArray(),
            $request,
        );

        AdminActivityLogger::write(
            $actorAdmin,
            'transfer_room',
            Contract::class,
            $destinationContract->id,
            null,
            $destinationContract->fresh()->toArray(),
            $request,
        );
    }

    private function notifyTenant(Tenant $tenant, Room $toRoom, Carbon $movementDate): void
    {
        Notification::create([
            'title' => 'Thông báo chuyển phòng',
            'content' => "Bạn đã được chuyển sang phòng {$toRoom->room_number} kể từ ngày {$movementDate->format('d/m/Y')}.",
            'notification_type' => 1, // TODO: map lại theo Enum NotificationType thật của bạn nếu có
            'target_type' => 4,       // 4 = cá nhân (đi kèm tenant_id), khớp với cách dùng trong dữ liệu mẫu
            'building_id' => $toRoom->building_id,
            'room_id' => $toRoom->id,
            'tenant_id' => $tenant->id,
            'published_at' => now(),
            'status' => 2, // khớp dữ liệu mẫu: 2 = đã publish
        ]);
    }

    private function generateContractCode(Room $room): string
    {
        // Đơn giản, đủ dùng cho MVP. Nếu lo va chạm khi nhiều request cùng giây,
        // nên đổi sang 1 bộ sinh mã có kiểm tra unique hoặc dùng uuid ngắn.
        return sprintf('HD-%s-%s', $room->slug ?? $room->room_number, now()->format('YmdHis'));
    }
}
