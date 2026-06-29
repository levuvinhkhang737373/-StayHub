<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ACTION_LABELS = [
        'add_deposit_transaction' => 'Thêm giao dịch tiền cọc',
        'analyze_meter_image' => 'Phân tích ảnh chỉ số điện nước',
        'assign_maintenance_staff' => 'Phân công nhân sự bảo trì',
        'bulk_generate_invoice_queued' => 'Xếp hàng tạo hóa đơn hàng loạt',
        'cancel_expense' => 'Hủy phiếu chi',
        'cancel_invoice' => 'Hủy hóa đơn',
        'change_password' => 'Đổi mật khẩu',
        'confirm_invoice_payment' => 'Xác nhận thanh toán hóa đơn',
        'create_admin_account' => 'Tạo tài khoản quản trị',
        'create_asset_template' => 'Tạo mẫu tài sản',
        'create_building' => 'Tạo tòa nhà',
        'create_contract' => 'Tạo hợp đồng',
        'create_expense' => 'Tạo phiếu chi',
        'create_expense_category' => 'Tạo danh mục chi phí',
        'create_meter_device' => 'Tạo đồng hồ điện nước',
        'create_notification' => 'Tạo thông báo',
        'create_region' => 'Tạo khu vực',
        'create_room' => 'Tạo phòng',
        'create_room_type' => 'Tạo loại phòng',
        'create_security_camera' => 'Tạo máy quay an ninh',
        'create_service' => 'Tạo dịch vụ',
        'create_setting' => 'Tạo cài đặt',
        'create_tenant' => 'Tạo khách thuê',
        'create_vehicle' => 'Tạo phương tiện',
        'delete_admin_account' => 'Xóa tài khoản quản trị',
        'delete_asset_template' => 'Xóa mẫu tài sản',
        'delete_building' => 'Xóa tòa nhà',
        'delete_contract' => 'Xóa hợp đồng',
        'delete_expense_category' => 'Xóa danh mục chi phí',
        'delete_faceid' => 'Xóa nhận diện khuôn mặt',
        'delete_meter_device' => 'Xóa đồng hồ điện nước',
        'delete_notification' => 'Xóa thông báo',
        'delete_region' => 'Xóa khu vực',
        'delete_room' => 'Xóa phòng',
        'delete_room_type' => 'Xóa loại phòng',
        'delete_security_camera' => 'Xóa máy quay an ninh',
        'delete_service' => 'Xóa dịch vụ',
        'delete_setting' => 'Xóa cài đặt',
        'delete_tenant' => 'Xóa khách thuê',
        'delete_vehicle' => 'Xóa phương tiện',
        'face_login_success' => 'Đăng nhập bằng nhận diện khuôn mặt thành công',
        'generate_and_issue_invoice' => 'Tạo và phát hành hóa đơn',
        'login_success' => 'Đăng nhập thành công',
        'logout' => 'Đăng xuất',
        'record_invoice_payment' => 'Ghi nhận thanh toán hóa đơn',
        'register_faceid' => 'Đăng ký nhận diện khuôn mặt',
        'reissue_invoice' => 'Phát hành lại hóa đơn',
        'renew_contract' => 'Gia hạn hợp đồng',
        'save_meter_reading' => 'Lưu chỉ số điện nước',
        'schedule_room_transfer' => 'Lên lịch chuyển phòng',
        'seed_create' => 'Tạo dữ liệu mẫu',
        'terminate_contract' => 'Thanh lý hợp đồng',
        'transfer_room' => 'Chuyển phòng',
        'update_admin_account' => 'Cập nhật tài khoản quản trị',
        'update_admin_account_status' => 'Cập nhật trạng thái tài khoản quản trị',
        'update_asset_template' => 'Cập nhật mẫu tài sản',
        'update_asset_template_status' => 'Cập nhật trạng thái mẫu tài sản',
        'update_building' => 'Cập nhật tòa nhà',
        'update_building_status' => 'Cập nhật trạng thái tòa nhà',
        'update_contract' => 'Cập nhật hợp đồng',
        'update_contract_status' => 'Cập nhật trạng thái hợp đồng',
        'update_expense' => 'Cập nhật phiếu chi',
        'update_expense_category' => 'Cập nhật danh mục chi phí',
        'update_expense_category_status' => 'Cập nhật trạng thái danh mục chi phí',
        'update_invoice' => 'Cập nhật hóa đơn',
        'update_maintenance_status' => 'Cập nhật trạng thái bảo trì',
        'update_meter_device' => 'Cập nhật đồng hồ điện nước',
        'update_meter_device_status' => 'Cập nhật trạng thái đồng hồ điện nước',
        'update_notification' => 'Cập nhật thông báo',
        'update_profile' => 'Cập nhật hồ sơ cá nhân',
        'update_region' => 'Cập nhật khu vực',
        'update_region_status' => 'Cập nhật trạng thái khu vực',
        'update_room' => 'Cập nhật phòng',
        'update_room_status' => 'Cập nhật trạng thái phòng',
        'update_room_type' => 'Cập nhật loại phòng',
        'update_room_type_status' => 'Cập nhật trạng thái loại phòng',
        'update_security_camera' => 'Cập nhật máy quay an ninh',
        'update_service' => 'Cập nhật dịch vụ',
        'update_service_status' => 'Cập nhật trạng thái dịch vụ',
        'update_setting' => 'Cập nhật cài đặt',
        'update_setting_public' => 'Cập nhật hiển thị cài đặt',
        'update_tenant' => 'Cập nhật khách thuê',
        'update_tenant_status' => 'Cập nhật trạng thái khách thuê',
        'update_utility_prices' => 'Cập nhật giá điện nước',
        'update_vehicle' => 'Cập nhật phương tiện',
        'update_vehicle_status' => 'Cập nhật trạng thái phương tiện',
    ];

    private const OLD_LABELS = [
        'Tạo tài khoản admin' => 'Tạo tài khoản quản trị',
        'Cập nhật tài khoản admin' => 'Cập nhật tài khoản quản trị',
        'Cập nhật trạng thái tài khoản admin' => 'Cập nhật trạng thái tài khoản quản trị',
        'Xóa tài khoản admin' => 'Xóa tài khoản quản trị',
        'Đăng nhập bằng FaceID thành công' => 'Đăng nhập bằng nhận diện khuôn mặt thành công',
        'Đăng ký FaceID' => 'Đăng ký nhận diện khuôn mặt',
        'Xóa FaceID' => 'Xóa nhận diện khuôn mặt',
        'Tạo camera an ninh' => 'Tạo máy quay an ninh',
        'Cập nhật camera an ninh' => 'Cập nhật máy quay an ninh',
        'Xóa camera an ninh' => 'Xóa máy quay an ninh',
    ];

    public function up(): void
    {
        foreach ([...self::ACTION_LABELS, ...self::OLD_LABELS] as $action => $label) {
            DB::table('admin_logs')
                ->where('action', $action)
                ->update(['action' => $label]);
        }
    }

    public function down(): void
    {
        foreach (array_flip(self::ACTION_LABELS) as $label => $action) {
            DB::table('admin_logs')
                ->where('action', $label)
                ->update(['action' => $action]);
        }
    }
};
