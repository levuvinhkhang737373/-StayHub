<?php

namespace App\Http\Resources\Admin;

use App\Models\Admin;
use App\Models\AssetTemplate;
use App\Models\Building;
use App\Models\BuildingImage;
use App\Models\Contract;
use App\Models\ContractDepositTransaction;
use App\Models\ContractTenant;
use App\Models\ContractVehicle;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\MaintenanceRequest;
use App\Models\MeterDevice;
use App\Models\MeterReading;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Region;
use App\Models\Room;
use App\Models\RoomImage;
use App\Models\RoomMovement;
use App\Models\RoomType;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminLogResource extends JsonResource
{
    private const MASKED_VALUE = '***';

    private const MASKED_DISPLAY_VALUE = 'Đã ẩn';

    private const SENSITIVE_KEYS = [
        'password',
        'token',
        'secret',
        'remember_token',
        'image_path_faceid',
    ];

    private const ENTITY_TYPE_LABELS = [
        'Admin' => 'Quản trị viên',
        'AssetTemplate' => 'Mẫu tài sản',
        'Building' => 'Tòa nhà',
        'Contract' => 'Hợp đồng',
        'Expense' => 'Phiếu chi',
        'ExpenseCategory' => 'Danh mục chi phí',
        'Invoice' => 'Hóa đơn',
        'MaintenanceRequest' => 'Phiếu bảo trì',
        'MeterDevice' => 'Đồng hồ điện nước',
        'MeterReading' => 'Chỉ số điện nước',
        'Notification' => 'Thông báo',
        'Payment' => 'Thanh toán',
        'Region' => 'Khu vực',
        'Room' => 'Phòng',
        'RoomMovement' => 'Biến động phòng',
        'RoomType' => 'Loại phòng',
        'SecurityCamera' => 'Máy quay an ninh',
        'Service' => 'Dịch vụ',
        'Setting' => 'Cài đặt',
        'Tenant' => 'Khách thuê',
        'Vehicle' => 'Phương tiện',
    ];

    private const DISPLAY_NAME_KEYS = [
        'full_name',
        'name',
        'title',
        'room_number',
        'contract_code',
        'invoice_code',
        'expense_code',
        'setting_label',
        'license_plate',
        'username',
        'email',
        'code',
        'slug',
    ];

    private const FIELD_LABELS = [
        'action' => 'Hành động',
        'actual_end_date' => 'Ngày kết thúc thực tế',
        'address' => 'Địa chỉ',
        'amount' => 'Số tiền',
        'area_m2' => 'Diện tích',
        'alert_cooldown_seconds' => 'Thời gian chờ giữa các cảnh báo',
        'avatar_url' => 'Ảnh đại diện',
        'back_image_url' => 'Ảnh mặt sau giấy tờ',
        'base_price' => 'Giá cơ bản',
        'billing_cycle_day' => 'Ngày chốt kỳ thanh toán',
        'billing_end_date' => 'Ngày kết thúc tính tiền',
        'billing_month' => 'Tháng thanh toán',
        'billing_start_date' => 'Ngày bắt đầu tính tiền',
        'billing_year' => 'Năm thanh toán',
        'brand' => 'Thương hiệu',
        'building' => 'Tòa nhà',
        'charge_method' => 'Cách tính phí',
        'charge_policy' => 'Chính sách tính phí',
        'code' => 'Mã',
        'color' => 'Màu sắc',
        'comment' => 'Bình luận',
        'completed_at' => 'Thời gian hoàn thành',
        'consumption' => 'Mức tiêu thụ',
        'content' => 'Nội dung',
        'contract' => 'Hợp đồng',
        'contract_code' => 'Mã hợp đồng',
        'contract_files' => 'Tệp hợp đồng',
        'created_at' => 'Thời gian tạo',
        'created_faceid_at' => 'Thời gian tạo nhận diện khuôn mặt',
        'current_address' => 'Địa chỉ hiện tại',
        'current_occupants' => 'Số người đang ở',
        'current_reading' => 'Chỉ số hiện tại',
        'date_of_birth' => 'Ngày sinh',
        'deduction_amount' => 'Số tiền khấu trừ',
        'default_unit_name' => 'Đơn vị mặc định',
        'deposit_amount' => 'Tiền cọc',
        'deposit_refund_amount' => 'Tiền cọc hoàn lại',
        'deposit_transfer_amount' => 'Tiền cọc chuyển tiếp',
        'description' => 'Mô tả',
        'due_date' => 'Hạn thanh toán',
        'effective_from' => 'Hiệu lực từ ngày',
        'effective_to' => 'Hiệu lực đến ngày',
        'email' => 'Email',
        'end_date' => 'Ngày kết thúc',
        'ended_at' => 'Ngày kết thúc',
        'expense_category' => 'Danh mục chi phí',
        'expense_code' => 'Mã phiếu chi',
        'expense_date' => 'Ngày chi',
        'final_electric_reading' => 'Chỉ số điện cuối',
        'final_water_reading' => 'Chỉ số nước cuối',
        'floor' => 'Tầng',
        'frame_interval_seconds' => 'Khoảng cách giữa các khung hình',
        'frames_per_batch' => 'Số khung hình mỗi lần phân tích',
        'front_image_url' => 'Ảnh mặt trước giấy tờ',
        'full_name' => 'Họ tên',
        'gender' => 'Giới tính',
        'gender_policy' => 'Chính sách giới tính',
        'identity_date' => 'Ngày cấp giấy tờ',
        'identity_number' => 'Số giấy tờ',
        'identity_place' => 'Nơi cấp giấy tờ',
        'identity_type' => 'Loại giấy tờ',
        'image_path' => 'Ảnh',
        'image_path_faceid' => 'Ảnh nhận diện khuôn mặt',
        'images' => 'Hình ảnh',
        'initial_reading' => 'Chỉ số ban đầu',
        'installed_at' => 'Ngày lắp đặt',
        'invoice' => 'Hóa đơn',
        'invoice_code' => 'Mã hóa đơn',
        'is_ai_enabled' => 'Bật trí tuệ nhân tạo',
        'is_active' => 'Trạng thái sử dụng',
        'is_primary' => 'Ảnh chính',
        'is_public' => 'Hiển thị công khai',
        'is_representative' => 'Người đại diện',
        'is_required' => 'Bắt buộc',
        'is_staying' => 'Tình trạng lưu trú',
        'issued_at' => 'Thời gian phát hành',
        'item_type' => 'Loại khoản thu',
        'join_date' => 'Ngày vào ở',
        'leave_date' => 'Ngày rời đi',
        'license_plate' => 'Biển số xe',
        'maintenance_request' => 'Phiếu bảo trì',
        'manager' => 'Quản lý',
        'max_occupants' => 'Số người tối đa',
        'meter_device' => 'Đồng hồ điện nước',
        'meter_type' => 'Loại đồng hồ',
        'monthly_fee' => 'Phí hằng tháng',
        'movement_date' => 'Ngày chuyển/trả phòng',
        'movement_type' => 'Loại biến động phòng',
        'name' => 'Tên',
        'new_status' => 'Trạng thái mới',
        'note' => 'Ghi chú',
        'notification_type' => 'Loại thông báo',
        'old_room_final_amount' => 'Tiền chốt phòng cũ',
        'old_status' => 'Trạng thái cũ',
        'paid_amount' => 'Đã thanh toán',
        'parent_contract' => 'Hợp đồng gốc',
        'password' => 'Mật khẩu',
        'password_changed' => 'Mật khẩu đã đổi',
        'path' => 'Đường dẫn phân cấp',
        'payload' => 'Dữ liệu lên lịch',
        'payment' => 'Thanh toán',
        'payment_code' => 'Mã thanh toán',
        'payment_date' => 'Ngày thanh toán',
        'payment_method' => 'Phương thức thanh toán',
        'payment_status' => 'Trạng thái thanh toán',
        'period_end' => 'Cuối kỳ',
        'period_start' => 'Đầu kỳ',
        'permanent_address' => 'Địa chỉ thường trú',
        'phone' => 'Số điện thoại',
        'previous_debt_amount' => 'Nợ kỳ trước',
        'previous_reading' => 'Chỉ số trước',
        'price' => 'Giá',
        'profile' => 'Hồ sơ',
        'proof_image' => 'Ảnh minh chứng',
        'published_at' => 'Thời gian gửi',
        'quantity' => 'Số lượng',
        'rating' => 'Đánh giá',
        'reading_date' => 'Ngày ghi chỉ số',
        'reason' => 'Lý do',
        'receipt_images' => 'Ảnh chứng từ',
        'received_at' => 'Thời gian tiếp nhận',
        'region' => 'Khu vực',
        'remaining_amount' => 'Còn phải thanh toán',
        'request_code' => 'Mã yêu cầu',
        'role' => 'Vai trò',
        'room' => 'Phòng',
        'room_number' => 'Số phòng',
        'room_price' => 'Giá phòng',
        'room_type' => 'Loại phòng',
        'service' => 'Dịch vụ',
        'setting_label' => 'Tên cài đặt',
        'setting_value' => 'Giá trị cài đặt',
        'slug' => 'Đường dẫn',
        'sort_order' => 'Thứ tự hiển thị',
        'source_type' => 'Loại nguồn máy quay',
        'start_date' => 'Ngày bắt đầu',
        'started_at' => 'Ngày bắt đầu',
        'stream_url' => 'Đường dẫn máy quay',
        'status' => 'Trạng thái',
        'target_type' => 'Đối tượng nhận',
        'tenant' => 'Khách thuê',
        'tenant_signature_url' => 'Chữ ký khách thuê',
        'tenant_signed_at' => 'Thời gian khách thuê ký',
        'title' => 'Tiêu đề',
        'token' => 'Mã xác thực',
        'total_amount' => 'Tổng tiền',
        'total_floors' => 'Tổng số tầng',
        'transaction_date' => 'Ngày giao dịch',
        'transaction_reference' => 'Mã tham chiếu giao dịch',
        'transaction_type' => 'Loại giao dịch',
        'transfer_fee' => 'Phí chuyển phòng',
        'unit_name' => 'Đơn vị',
        'unit_price' => 'Đơn giá',
        'updated_at' => 'Thời gian cập nhật',
        'updated_faceid_at' => 'Thời gian cập nhật nhận diện khuôn mặt',
        'user_agent' => 'Thiết bị truy cập',
        'username' => 'Tên đăng nhập',
        'value' => 'Giá trị',
        'vehicle' => 'Phương tiện',
        'vehicle_type' => 'Loại phương tiện',
    ];

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'admin_name' => $this->adminName(),
            'admin' => $this->whenLoaded('admin', fn (): ?array => $this->admin ? [
                'id' => $this->admin->id,
                'username' => $this->admin->username,
                'full_name' => $this->admin->full_name,
                'display_name' => $this->adminName(),
                'email' => $this->admin->email,
                'role' => $this->admin->role,
                'role_label' => Admin::ROLE_LABELS[$this->admin->role] ?? null,
                'status' => $this->admin->status,
                'status_label' => Admin::STATUS_LABELS[$this->admin->status] ?? null,
            ] : null),
            'action' => $this->action,
            'entity_type' => $this->entity_type,
            'entity_type_label' => $this->entityTypeLabel(),
            'entity_id' => $this->entity_id,
            'entity_name' => $this->entityName(),
            'old_data' => $this->maskSensitiveData($this->old_data),
            'new_data' => $this->maskSensitiveData($this->new_data),
            'changed_fields' => $this->changedFields($this->old_data, $this->new_data),
            'old_data_display' => $this->displayData($this->old_data),
            'new_data_display' => $this->displayData($this->new_data),
            'changed_fields_display' => $this->changedFieldLabels($this->old_data, $this->new_data),
            'change_summary' => $this->changeSummary($this->old_data, $this->new_data),
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'created_at' => optional($this->created_at)->toDateTimeString(),
        ];
    }

    private function adminName(): ?string
    {
        if (! $this->relationLoaded('admin') || ! $this->admin) {
            return null;
        }

        $adminName = $this->firstFilledValue([
            $this->admin->full_name,
            $this->admin->username,
            $this->admin->email,
        ]);

        if (! $adminName) {
            return null;
        }

        if ((int) $this->admin->role === Admin::ROLE_SUPER_ADMIN) {
            return 'Quản trị tổng - '.$adminName;
        }

        if ((int) $this->admin->role === Admin::ROLE_BUILDING_MANAGER) {
            return $this->managedBuildingNames().' - '.$adminName;
        }

        return $adminName;
    }

    private function managedBuildingNames(): string
    {
        if (! $this->admin->relationLoaded('managedBuildings')) {
            return Admin::ROLE_LABELS[Admin::ROLE_BUILDING_MANAGER];
        }

        $buildingNames = $this->admin->managedBuildings
            ->pluck('name')
            ->filter(fn (mixed $name): bool => $this->isFilledScalar($name))
            ->values();

        if ($buildingNames->isEmpty()) {
            return Admin::ROLE_LABELS[Admin::ROLE_BUILDING_MANAGER];
        }

        return $buildingNames->join(', ');
    }

    private function maskSensitiveData(mixed $data): mixed
    {
        if (! is_array($data)) {
            return $data;
        }

        return collect($data)
            ->mapWithKeys(function (mixed $value, string|int $key): array {
                if ($this->isSensitiveKey((string) $key)) {
                    return [$key => self::MASKED_VALUE];
                }

                return [$key => $this->maskSensitiveData($value)];
            })
            ->all();
    }

    private function entityTypeLabel(): string
    {
        $shortName = class_basename((string) $this->entity_type);

        return self::ENTITY_TYPE_LABELS[$shortName] ?? $shortName;
    }

    private function entityName(): ?string
    {
        $displayName = $this->displayNameFromData($this->new_data)
            ?? $this->displayNameFromData($this->old_data);

        if ($displayName) {
            return $displayName;
        }

        if (class_basename((string) $this->entity_type) === 'Admin' && $this->relationLoaded('admin')) {
            return $this->admin?->full_name ?: $this->admin?->username;
        }

        return null;
    }

    private function displayNameFromData(?array $data): ?string
    {
        if (! $data) {
            return null;
        }

        foreach (self::DISPLAY_NAME_KEYS as $key) {
            $value = $data[$key] ?? null;

            if ($this->isFilledScalar($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    private function firstFilledValue(array $values): ?string
    {
        foreach ($values as $value) {
            if ($this->isFilledScalar($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    private function isFilledScalar(mixed $value): bool
    {
        return is_scalar($value) && filled((string) $value);
    }

    private function changedFields(?array $oldData, ?array $newData): array
    {
        if (! $oldData && ! $newData) {
            return [];
        }

        $oldData ??= [];
        $newData ??= [];

        return collect(array_unique([...array_keys($oldData), ...array_keys($newData)]))
            ->filter(fn (string|int $key): bool => $this->normalizeValue($oldData[$key] ?? null) !== $this->normalizeValue($newData[$key] ?? null))
            ->values()
            ->all();
    }

    private function displayData(?array $data): array
    {
        if (! $data) {
            return [];
        }

        return array_values($this->flattenDataForDisplay($data));
    }

    private function changeSummary(?array $oldData, ?array $newData): array
    {
        $oldValues = $this->flattenRawValues($oldData ?? []);
        $newValues = $this->flattenRawValues($newData ?? []);
        $paths = array_values(array_unique([...array_keys($oldValues), ...array_keys($newValues)]));

        return collect($paths)
            ->filter(fn (string $path): bool => $this->normalizeValue($oldValues[$path] ?? null) !== $this->normalizeValue($newValues[$path] ?? null))
            ->map(fn (string $path): array => [
                'key' => $path,
                'label' => $this->fieldLabel($path),
                'old_value' => $this->displaySafeValue($path, $oldValues[$path] ?? null),
                'new_value' => $this->displaySafeValue($path, $newValues[$path] ?? null),
            ])
            ->values()
            ->all();
    }

    private function changedFieldLabels(?array $oldData, ?array $newData): array
    {
        return collect($this->changeSummary($oldData, $newData))
            ->pluck('label')
            ->unique()
            ->values()
            ->all();
    }

    private function flattenDataForDisplay(array $data, string $parentPath = ''): array
    {
        $entries = [];

        foreach ($data as $key => $value) {
            $path = $this->nestedPath($parentPath, (string) $key);

            if ($this->shouldHideDisplayField($path)) {
                continue;
            }

            if (is_array($value) && ! array_is_list($value)) {
                $entries = [...$entries, ...$this->flattenDataForDisplay($value, $path)];
                continue;
            }

            $entries[$path] = [
                'key' => $path,
                'label' => $this->fieldLabel($path),
                'value' => $this->displaySafeValue($path, $value),
            ];
        }

        return $entries;
    }

    private function flattenRawValues(array $data, string $parentPath = ''): array
    {
        $values = [];

        foreach ($data as $key => $value) {
            $path = $this->nestedPath($parentPath, (string) $key);

            if ($this->shouldHideDisplayField($path)) {
                continue;
            }

            if (is_array($value) && ! array_is_list($value)) {
                $values = [...$values, ...$this->flattenRawValues($value, $path)];
                continue;
            }

            $values[$path] = $value;
        }

        return $values;
    }

    private function nestedPath(string $parentPath, string $key): string
    {
        return $parentPath === '' ? $key : $parentPath.'.'.$key;
    }

    private function shouldHideDisplayField(string $path): bool
    {
        $field = $this->lastPathSegment($path);

        return $field === 'id'
            || str_ends_with($field, '_id')
            || in_array($field, ['created_by', 'uploaded_by', 'assigned_to', 'collected_by'], true);
    }

    private function fieldLabel(string $path): string
    {
        return collect(explode('.', $path))
            ->reject(fn (string $segment): bool => is_numeric($segment))
            ->map(fn (string $segment): string => self::FIELD_LABELS[$segment] ?? 'Thông tin khác')
            ->join(' / ');
    }

    private function displaySafeValue(string $path, mixed $value): string
    {
        if ($this->isSensitivePath($path)) {
            return self::MASKED_DISPLAY_VALUE;
        }

        return $this->displayValue($path, $value);
    }

    private function displayValue(string $path, mixed $value): string
    {
        if ($value === self::MASKED_VALUE) {
            return self::MASKED_DISPLAY_VALUE;
        }

        if ($value === null || $value === '') {
            return 'Không có';
        }

        if (is_bool($value)) {
            return $value ? 'Có' : 'Không';
        }

        if (is_array($value)) {
            return count($value).' mục';
        }

        $field = $this->lastPathSegment($path);
        $valueLabel = $this->valueLabel($field, $value);

        if ($valueLabel) {
            return $valueLabel;
        }

        if ($this->isMoneyField($field) && is_numeric($value)) {
            return number_format((float) $value, 0, ',', '.').' đ';
        }

        if ($field === 'area_m2' && is_numeric($value)) {
            return number_format((float) $value, 2, ',', '.').' m²';
        }

        if ($this->isDateField($field) && is_scalar($value)) {
            return $this->formatDateValue((string) $value, str_ends_with($field, '_at'));
        }

        return (string) $value;
    }

    private function valueLabel(string $field, mixed $value): ?string
    {
        $labels = $this->valueLabelsForField($field);

        if (! $labels) {
            return null;
        }

        foreach ($this->possibleLabelKeys($value) as $key) {
            if (array_key_exists($key, $labels)) {
                return $labels[$key];
            }
        }

        return null;
    }

    private function valueLabelsForField(string $field): ?array
    {
        return match ($field) {
            'role' => Admin::ROLE_LABELS,
            'gender' => $this->entityShortName() === 'Tenant' ? Tenant::GENDER_LABELS : Admin::GENDER_LABELS,
            'gender_policy' => Building::GENDER_POLICY_LABELS,
            'identity_type' => Tenant::IDENTITY_TYPE_LABELS,
            'vehicle_type' => Vehicle::VEHICLE_TYPE_LABELS,
            'meter_type' => MeterDevice::METER_TYPE_LABELS,
            'notification_type' => Notification::NOTIFICATION_TYPE_LABELS,
            'target_type' => Notification::TARGET_TYPE_LABELS,
            'transaction_type' => ContractDepositTransaction::TRANSACTION_TYPE_LABELS,
            'movement_type' => RoomMovement::MOVEMENT_TYPE_LABELS,
            'charge_method' => Service::CHARGE_METHOD_LABELS,
            'charge_policy' => ContractVehicle::CHARGE_POLICY_LABELS,
            'item_type' => InvoiceItem::ITEM_TYPE_LABELS,
            'payment_status' => Contract::PAYMENT_STATUS_LABELS,
            'payment_method' => $this->paymentMethodLabels(),
            'is_required' => Service::REQUIRED_LABELS,
            'is_public' => Setting::PUBLIC_LABELS,
            'is_primary' => $this->imagePrimaryLabels(),
            'is_staying' => ContractTenant::STAYING_LABELS,
            'is_active' => $this->activeLabels(),
            'old_status', 'new_status' => MaintenanceRequest::STATUS_LABELS,
            'status' => $this->statusLabels(),
            default => null,
        };
    }

    private function possibleLabelKeys(mixed $value): array
    {
        $keys = [$value];

        if (is_bool($value)) {
            $keys[] = (int) $value;
            $keys[] = (string) (int) $value;
        }

        if (is_numeric($value)) {
            $keys[] = (int) $value;
            $keys[] = (string) (int) $value;
        }

        return array_values(array_unique($keys, SORT_REGULAR));
    }

    private function statusLabels(): ?array
    {
        return match ($this->entityShortName()) {
            'Admin' => Admin::STATUS_LABELS,
            'AssetTemplate' => AssetTemplate::STATUS_LABELS,
            'Building' => Building::STATUS_LABELS,
            'BuildingImage' => BuildingImage::STATUS_LABELS,
            'Contract' => Contract::STATUS_LABELS,
            'Expense' => Expense::STATUS_LABELS,
            'Invoice' => Invoice::STATUS_LABELS,
            'MaintenanceRequest' => MaintenanceRequest::STATUS_LABELS,
            'MeterDevice' => MeterDevice::STATUS_LABELS,
            'MeterReading' => MeterReading::STATUS_LABELS,
            'Notification' => Notification::STATUS_LABELS,
            'Payment' => Payment::STATUS_LABELS,
            'Room' => Room::STATUS_LABELS,
            'RoomImage' => RoomImage::STATUS_LABELS,
            'RoomType' => RoomType::STATUS_LABELS,
            'ServicePrice' => ServicePrice::STATUS_LABELS,
            'Tenant' => Tenant::STATUS_LABELS,
            default => null,
        };
    }

    private function activeLabels(): ?array
    {
        return match ($this->entityShortName()) {
            'ExpenseCategory' => ExpenseCategory::ACTIVE_LABELS,
            'Region' => Region::ACTIVE_LABELS,
            'Service' => Service::ACTIVE_LABELS,
            'Vehicle' => Vehicle::ACTIVE_LABELS,
            default => null,
        };
    }

    private function paymentMethodLabels(): array
    {
        return match ($this->entityShortName()) {
            'ContractDepositTransaction' => ContractDepositTransaction::PAYMENT_METHOD_LABELS,
            'Expense' => Expense::PAYMENT_METHOD_LABELS,
            default => Payment::PAYMENT_METHOD_LABELS,
        };
    }

    private function imagePrimaryLabels(): array
    {
        return $this->entityShortName() === 'BuildingImage'
            ? BuildingImage::PRIMARY_LABELS
            : RoomImage::PRIMARY_LABELS;
    }

    private function isMoneyField(string $field): bool
    {
        return str_contains($field, 'amount')
            || str_contains($field, 'price')
            || str_contains($field, 'fee')
            || str_contains($field, 'debt');
    }

    private function isDateField(string $field): bool
    {
        return str_ends_with($field, '_at')
            || str_ends_with($field, '_date')
            || str_ends_with($field, '_from')
            || str_ends_with($field, '_to')
            || in_array($field, ['start_date', 'end_date', 'due_date', 'period_start', 'period_end'], true);
    }

    private function formatDateValue(string $value, bool $withTime): string
    {
        try {
            return Carbon::parse($value)->format($withTime ? 'd/m/Y H:i' : 'd/m/Y');
        } catch (\Exception) {
            return $value;
        }
    }

    private function lastPathSegment(string $path): string
    {
        $segments = explode('.', $path);

        return (string) end($segments);
    }

    private function entityShortName(): string
    {
        return class_basename((string) $this->entity_type);
    }

    private function isSensitivePath(string $path): bool
    {
        return collect(explode('.', $path))
            ->contains(fn (string $segment): bool => $this->isSensitiveKey($segment));
    }

    private function normalizeValue(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalizedKey = strtolower($key);

        return collect(self::SENSITIVE_KEYS)
            ->contains(fn (string $sensitiveKey): bool => str_contains($normalizedKey, $sensitiveKey));
    }
}
