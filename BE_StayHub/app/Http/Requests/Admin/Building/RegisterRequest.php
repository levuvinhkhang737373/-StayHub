<?php

namespace App\Http\Requests\Admin\Building;

use App\Helpers\ApiResponse;
use App\Models\Admin;
use App\Models\AssetTemplate;
use App\Models\Building;
use App\Models\BuildingImage;
use App\Models\RoomType;
use App\Models\ServicePrice;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'region_id' => ['required', 'integer', 'exists:regions,id'],
            'manager_admin_id' => [
                'nullable',
                'integer',
                Rule::exists('admins', 'id')->where('role', Admin::ROLE_BUILDING_MANAGER)->where('status', Admin::STATUS_ACTIVE),
            ],
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
            'total_floors' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'gender_policy' => ['nullable', 'integer', Rule::in(array_keys(Building::GENDER_POLICY_LABELS))],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'integer', Rule::in(array_keys(Building::STATUS_LABELS))],
            'images' => ['nullable', 'array', 'max:20'],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'image_metadata' => ['nullable', 'array'],
            'image_metadata.*.is_primary' => ['nullable', 'boolean'],
            'image_metadata.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'image_metadata.*.status' => ['nullable', 'integer', Rule::in(array_keys(BuildingImage::STATUS_LABELS))],
            'room_type_ids' => ['nullable', 'array', 'max:100'],
            'room_type_ids.*' => ['required', 'integer', 'distinct', Rule::exists('room_types', 'id')],
            'room_types' => ['nullable', 'array', 'max:100'],
            'room_types.*.name' => ['required_with:room_types', 'string', 'max:150'],
            'room_types.*.description' => ['nullable', 'string'],
            'room_types.*.status' => ['nullable', 'integer', Rule::in(array_keys(RoomType::STATUS_LABELS))],
            'asset_template_ids' => ['nullable', 'array', 'max:100'],
            'asset_template_ids.*' => ['required', 'integer', 'distinct', Rule::exists('asset_templates', 'id')],
            'asset_templates' => ['nullable', 'array', 'max:100'],
            'asset_templates.*.name' => ['required_with:asset_templates', 'string', 'max:150'],
            'asset_templates.*.default_unit_name' => ['nullable', 'integer', Rule::in(array_keys(AssetTemplate::UNIT_LABELS))],
            'asset_templates.*.description' => ['nullable', 'string'],
            'asset_templates.*.status' => ['nullable', 'integer', Rule::in(array_keys(AssetTemplate::STATUS_LABELS))],
            'service_prices' => ['nullable', 'array', 'max:100'],
            'service_prices.*.service_id' => ['required_with:service_prices', 'integer', 'exists:services,id'],
            'service_prices.*.price' => ['required_with:service_prices', 'regex:/^\d+(\.\d{1,2})?$/'],
            'service_prices.*.effective_from' => ['nullable', 'date'],
            'service_prices.*.effective_to' => ['nullable', 'date'],
            'service_prices.*.status' => ['nullable', 'integer', Rule::in(array_keys(ServicePrice::STATUS_LABELS))],
            'setting_ids' => ['nullable', 'array', 'max:100'],
            'setting_ids.*' => ['required', 'integer', 'distinct', Rule::exists('settings', 'id')->whereNull('building_id')],
            'settings' => ['nullable', 'array', 'max:100'],
            'settings.*.setting_label' => ['required_with:settings', 'string', 'max:150'],
            'settings.*.setting_name' => ['required_with:settings', 'string', 'max:255', 'regex:/^[A-Za-z0-9_.-]+$/'],
            'settings.*.setting_value' => ['nullable', 'string', 'max:500'],
            'settings.*.description' => ['nullable', 'string', 'max:500'],
            'settings.*.is_public' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            foreach ($this->duplicateIndexes('room_types', 'name') as $index) {
                $validator->errors()->add("room_types.{$index}.name", 'Tên loại phòng không được trùng trong cùng tòa nhà.');
            }

            foreach ($this->duplicateIndexes('settings', 'setting_name') as $index) {
                $validator->errors()->add("settings.{$index}.setting_name", 'Khóa cài đặt không được trùng trong cùng tòa nhà.');
            }

            foreach ($this->servicePriceRows() as $index => $servicePrice) {
                if (! is_array($servicePrice)) {
                    continue;
                }

                if (array_key_exists('price', $servicePrice) && ! is_string($servicePrice['price'])) {
                    $validator->errors()->add("service_prices.{$index}.price", 'Số tiền không hợp lệ.');
                }

                $from = $servicePrice['effective_from'] ?? null;
                $to = $servicePrice['effective_to'] ?? null;

                if ($from && $to && strtotime((string) $to) < strtotime((string) $from)) {
                    $validator->errors()->add("service_prices.{$index}.effective_to", 'Ngày hết hiệu lực không được trước ngày bắt đầu hiệu lực.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'region_id.required' => 'Khu vực của tòa nhà là bắt buộc.',
            'region_id.integer' => 'Khu vực của tòa nhà không hợp lệ.',
            'region_id.exists' => 'Khu vực của tòa nhà không tồn tại.',
            'manager_admin_id.integer' => 'Quản lý tòa nhà không hợp lệ.',
            'manager_admin_id.exists' => 'Quản lý tòa nhà phải là quản lý đang hoạt động trong hệ thống.',
            'name.required' => 'Tên tòa nhà là bắt buộc.',
            'name.string' => 'Tên tòa nhà phải là chuỗi ký tự.',
            'name.max' => 'Tên tòa nhà không được vượt quá 150 ký tự.',
            'address.string' => 'Địa chỉ tòa nhà phải là chuỗi ký tự.',
            'address.max' => 'Địa chỉ tòa nhà không được vượt quá 500 ký tự.',
            'total_floors.integer' => 'Tổng số tầng phải là số nguyên.',
            'total_floors.min' => 'Tổng số tầng phải lớn hơn hoặc bằng 1.',
            'total_floors.max' => 'Tổng số tầng không được vượt quá 1000.',
            'gender_policy.integer' => 'Chính sách giới tính của tòa nhà không hợp lệ.',
            'gender_policy.in' => 'Chính sách giới tính của tòa nhà không nằm trong danh sách cho phép.',
            'description.string' => 'Mô tả tòa nhà phải là chuỗi ký tự.',
            'status.integer' => 'Trạng thái tòa nhà không hợp lệ.',
            'status.in' => 'Trạng thái tòa nhà không nằm trong danh sách cho phép.',
            'images.array' => 'Danh sách ảnh tòa nhà không hợp lệ.',
            'images.max' => 'Mỗi lần chỉ được tải lên tối đa 20 ảnh tòa nhà.',
            'images.*.required' => 'File ảnh tòa nhà là bắt buộc.',
            'images.*.image' => 'File tải lên phải là ảnh hợp lệ.',
            'images.*.mimes' => 'Ảnh tòa nhà chỉ hỗ trợ định dạng jpg, jpeg, png hoặc webp.',
            'images.*.max' => 'Dung lượng mỗi ảnh tòa nhà không được vượt quá 10MB.',
            'image_metadata.array' => 'Thông tin cấu hình ảnh tòa nhà không hợp lệ.',
            'image_metadata.*.is_primary.boolean' => 'Trạng thái ảnh đại diện không hợp lệ.',
            'image_metadata.*.sort_order.integer' => 'Thứ tự ảnh tòa nhà phải là số nguyên.',
            'image_metadata.*.sort_order.min' => 'Thứ tự ảnh tòa nhà phải lớn hơn hoặc bằng 0.',
            'image_metadata.*.sort_order.max' => 'Thứ tự ảnh tòa nhà không được vượt quá 100000.',
            'image_metadata.*.status.integer' => 'Trạng thái ảnh tòa nhà không hợp lệ.',
            'image_metadata.*.status.in' => 'Trạng thái ảnh tòa nhà không nằm trong danh sách cho phép.',
            'room_type_ids.array' => 'Danh sách loại phòng chọn sẵn không hợp lệ.',
            'room_type_ids.max' => 'Mỗi lần chỉ được chọn tối đa 100 loại phòng mẫu.',
            'room_type_ids.*.required' => 'ID loại phòng mẫu là bắt buộc.',
            'room_type_ids.*.integer' => 'ID loại phòng mẫu không hợp lệ.',
            'room_type_ids.*.distinct' => 'Danh sách loại phòng mẫu không được trùng lặp.',
            'room_type_ids.*.exists' => 'Loại phòng mẫu không tồn tại hoặc đã thuộc tòa nhà khác.',
            'room_types.array' => 'Danh sách loại phòng không hợp lệ.',
            'room_types.max' => 'Mỗi tòa nhà chỉ được gửi tối đa 100 loại phòng mỗi lần.',
            'room_types.*.name.required_with' => 'Tên loại phòng là bắt buộc.',
            'room_types.*.name.string' => 'Tên loại phòng phải là chuỗi ký tự.',
            'room_types.*.name.max' => 'Tên loại phòng không được vượt quá 150 ký tự.',
            'room_types.*.description.string' => 'Mô tả loại phòng phải là chuỗi ký tự.',
            'room_types.*.status.integer' => 'Trạng thái loại phòng không hợp lệ.',
            'room_types.*.status.in' => 'Trạng thái loại phòng không nằm trong danh sách cho phép.',
            'asset_template_ids.array' => 'Danh sách mẫu tài sản chọn sẵn không hợp lệ.',
            'asset_template_ids.max' => 'Mỗi lần chỉ được chọn tối đa 100 mẫu tài sản.',
            'asset_template_ids.*.required' => 'ID mẫu tài sản chọn sẵn là bắt buộc.',
            'asset_template_ids.*.integer' => 'ID mẫu tài sản chọn sẵn không hợp lệ.',
            'asset_template_ids.*.distinct' => 'Danh sách mẫu tài sản chọn sẵn không được trùng lặp.',
            'asset_template_ids.*.exists' => 'Mẫu tài sản chọn sẵn không tồn tại hoặc đã thuộc tòa nhà khác.',
            'asset_templates.array' => 'Danh sách mẫu tài sản không hợp lệ.',
            'asset_templates.max' => 'Mỗi tòa nhà chỉ được gửi tối đa 100 mẫu tài sản mỗi lần.',
            'asset_templates.*.name.required_with' => 'Tên mẫu tài sản là bắt buộc.',
            'asset_templates.*.name.string' => 'Tên mẫu tài sản phải là chuỗi ký tự.',
            'asset_templates.*.name.max' => 'Tên mẫu tài sản không được vượt quá 150 ký tự.',
            'asset_templates.*.default_unit_name.integer' => 'Đơn vị mặc định của mẫu tài sản không hợp lệ.',
            'asset_templates.*.default_unit_name.in' => 'Đơn vị mặc định của mẫu tài sản không nằm trong danh sách cho phép.',
            'asset_templates.*.description.string' => 'Mô tả mẫu tài sản phải là chuỗi ký tự.',
            'asset_templates.*.status.integer' => 'Trạng thái mẫu tài sản không hợp lệ.',
            'asset_templates.*.status.in' => 'Trạng thái mẫu tài sản không nằm trong danh sách cho phép.',
            'service_prices.array' => 'Danh sách bảng giá dịch vụ không hợp lệ.',
            'service_prices.max' => 'Mỗi tòa nhà chỉ được gửi tối đa 100 bảng giá dịch vụ mỗi lần.',
            'service_prices.*.service_id.required_with' => 'Dịch vụ của bảng giá là bắt buộc.',
            'service_prices.*.service_id.integer' => 'Dịch vụ của bảng giá không hợp lệ.',
            'service_prices.*.service_id.exists' => 'Dịch vụ của bảng giá không tồn tại.',
            'service_prices.*.price.required_with' => 'Giá dịch vụ là bắt buộc.',
            'service_prices.*.price.regex' => 'Số tiền không hợp lệ.',
            'service_prices.*.effective_from.date' => 'Ngày bắt đầu hiệu lực không hợp lệ.',
            'service_prices.*.effective_to.date' => 'Ngày hết hiệu lực không hợp lệ.',
            'service_prices.*.status.integer' => 'Trạng thái bảng giá dịch vụ không hợp lệ.',
            'service_prices.*.status.in' => 'Trạng thái bảng giá dịch vụ không nằm trong danh sách cho phép.',
            'setting_ids.array' => 'Danh sách cài đặt chọn sẵn không hợp lệ.',
            'setting_ids.max' => 'Mỗi lần chỉ được chọn tối đa 100 cài đặt mẫu.',
            'setting_ids.*.required' => 'ID cài đặt mẫu là bắt buộc.',
            'setting_ids.*.integer' => 'ID cài đặt mẫu không hợp lệ.',
            'setting_ids.*.distinct' => 'Danh sách cài đặt mẫu không được trùng lặp.',
            'setting_ids.*.exists' => 'Cài đặt mẫu không tồn tại hoặc đã thuộc tòa nhà khác.',
            'settings.array' => 'Danh sách cài đặt không hợp lệ.',
            'settings.max' => 'Mỗi tòa nhà chỉ được gửi tối đa 100 cài đặt mỗi lần.',
            'settings.*.setting_label.required_with' => 'Tên hiển thị cài đặt là bắt buộc.',
            'settings.*.setting_label.string' => 'Tên hiển thị cài đặt phải là chuỗi ký tự.',
            'settings.*.setting_label.max' => 'Tên hiển thị cài đặt không được vượt quá 150 ký tự.',
            'settings.*.setting_name.required_with' => 'Khóa cài đặt là bắt buộc.',
            'settings.*.setting_name.string' => 'Khóa cài đặt phải là chuỗi ký tự.',
            'settings.*.setting_name.max' => 'Khóa cài đặt không được vượt quá 255 ký tự.',
            'settings.*.setting_name.regex' => 'Khóa cài đặt chỉ được chứa chữ, số, dấu gạch dưới, gạch ngang hoặc dấu chấm.',
            'settings.*.setting_value.string' => 'Giá trị cài đặt phải là chuỗi ký tự.',
            'settings.*.setting_value.max' => 'Giá trị cài đặt không được vượt quá 500 ký tự.',
            'settings.*.description.string' => 'Mô tả cài đặt phải là chuỗi ký tự.',
            'settings.*.description.max' => 'Mô tả cài đặt không được vượt quá 500 ký tự.',
            'settings.*.is_public.boolean' => 'Trạng thái hiển thị của cài đặt không hợp lệ.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }

    private function duplicateIndexes(string $arrayKey, string $field): array
    {
        $seen = [];
        $duplicates = [];

        foreach ($this->inputArray($arrayKey) as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $value = mb_strtolower(trim((string) ($item[$field] ?? '')));

            if ($value === '') {
                continue;
            }

            if (isset($seen[$value])) {
                $duplicates[] = $index;
            }

            $seen[$value] = true;
        }

        return $duplicates;
    }

    private function servicePriceRows(): array
    {
        return $this->inputArray('service_prices');
    }

    private function inputArray(string $key): array
    {
        $value = $this->input($key, []);

        return is_array($value) ? $value : [];
    }
}
