<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AdminScope;
use App\Helpers\ApiResponse;
use App\Helpers\ImageHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Room\RoomRequest;
use App\Models\Admin;
use App\Models\AssetTemplate;
use App\Models\Building;
use App\Models\Room;
use App\Models\RoomAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            $roomImages = $room->images;
            $room->assets()->delete();
            $room->images()->delete();
            $room->delete();
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
    public function updateStatus(string $id)
    {
        $room = Room::find($id);
        if (!$room) {
            return ApiResponse::responseJson(false, 'Không thể tìm thấy phòng', 404, null, 404);
        }
        $update_status_for_room = $room->update([
            'status' => $room->status ==1 ? 3 : 1
        ]);
        return ApiResponse::responseJson(true, "Cập nhật trạng thái phòng thành công", 200, null, 200);
    }
}
