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

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $buildingId = $this->route('building');

        return [
            'region_id' => ['sometimes', 'required', 'integer', 'exists:regions,id'],
            'manager_admin_id' => [
                'nullable',
                'integer',
                Rule::exists('admins', 'id')->where('role', Admin::ROLE_BUILDING_MANAGER)->where('status', Admin::STATUS_ACTIVE),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
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
            'delete_image_ids' => ['nullable', 'array', 'max:100'],
            'delete_image_ids.*' => ['required', 'integer', 'distinct', Rule::exists('building_images', 'id')->where('building_id', $buildingId)],
            'primary_image_id' => ['nullable', 'integer', Rule::exists('building_images', 'id')->where('building_id', $buildingId)],
            'service_prices' => ['nullable', 'array', 'max:100'],
            'service_prices.*.id' => ['nullable', 'integer', 'distinct', Rule::exists('service_prices', 'id')->where('building_id', $buildingId)],
            'service_prices.*.service_id' => ['required_with:service_prices', 'integer', 'exists:services,id'],
            'service_prices.*.price' => ['required_with:service_prices', 'regex:/^\d+(\.\d{1,2})?$/'],
            'service_prices.*.effective_from' => ['nullable', 'date'],
            'service_prices.*.effective_to' => ['nullable', 'date'],
            'service_prices.*.status' => ['nullable', 'integer', Rule::in(array_keys(ServicePrice::STATUS_LABELS))],
            'delete_service_price_ids' => ['nullable', 'array', 'max:100'],
            'delete_service_price_ids.*' => ['required', 'integer', 'distinct', Rule::exists('service_prices', 'id')->where('building_id', $buildingId)],
            'setting_ids' => ['nullable', 'array', 'max:100'],
            'setting_ids.*' => ['required', 'integer', 'distinct', Rule::exists('settings', 'id')->whereNull('building_id')],
            'settings' => ['nullable', 'array', 'max:100'],
            'settings.*.id' => ['nullable', 'integer', 'distinct', Rule::exists('settings', 'id')->where('building_id', $buildingId)],
            'settings.*.setting_label' => ['required_with:settings', 'string', 'max:150'],
            'settings.*.setting_name' => ['required_with:settings', 'string', 'max:255', 'regex:/^[A-Za-z0-9_.-]+$/'],
            'settings.*.setting_value' => ['nullable', 'string', 'max:500'],
            'settings.*.description' => ['nullable', 'string', 'max:500'],
            'settings.*.is_public' => ['nullable', 'boolean'],
            'delete_setting_ids' => ['nullable', 'array', 'max:100'],
            'delete_setting_ids.*' => ['required', 'integer', 'distinct', Rule::exists('settings', 'id')->where('building_id', $buildingId)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
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
            'region_id.required' => 'Khu vực của tòa nhà là bắt buộc khi cập nhật.',
            'region_id.integer' => 'Khu vực của tòa nhà không hợp lệ.',
            'region_id.exists' => 'Khu vực của tòa nhà không tồn tại.',
            'manager_admin_id.integer' => 'Quản lý tòa nhà không hợp lệ.',
            'manager_admin_id.exists' => 'Quản lý tòa nhà phải là quản lý đang hoạt động trong hệ thống.',
            'name.required' => 'Tên tòa nhà là bắt buộc khi cập nhật.',
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
            'delete_image_ids.array' => 'Danh sách ảnh cần xóa không hợp lệ.',
            'delete_image_ids.max' => 'Mỗi lần chỉ được xóa tối đa 100 ảnh tòa nhà.',
            'delete_image_ids.*.required' => 'ID ảnh tòa nhà cần xóa là bắt buộc.',
            'delete_image_ids.*.integer' => 'ID ảnh tòa nhà cần xóa không hợp lệ.',
            'delete_image_ids.*.distinct' => 'Danh sách ảnh cần xóa không được trùng lặp.',
            'delete_image_ids.*.exists' => 'Ảnh tòa nhà cần xóa không tồn tại.',
            'primary_image_id.integer' => 'Ảnh đại diện tòa nhà không hợp lệ.',
            'primary_image_id.exists' => 'Ảnh đại diện tòa nhà không tồn tại.',
            'service_prices.array' => 'Danh sách bảng giá dịch vụ không hợp lệ.',
            'service_prices.max' => 'Mỗi tòa nhà chỉ được gửi tối đa 100 bảng giá dịch vụ mỗi lần.',
            'service_prices.*.id.integer' => 'ID bảng giá dịch vụ không hợp lệ.',
            'service_prices.*.id.distinct' => 'Danh sách bảng giá dịch vụ không được trùng ID.',
            'service_prices.*.id.exists' => 'Bảng giá dịch vụ không tồn tại trong tòa nhà này.',
            'service_prices.*.service_id.required_with' => 'Dịch vụ của bảng giá là bắt buộc.',
            'service_prices.*.service_id.integer' => 'Dịch vụ của bảng giá không hợp lệ.',
            'service_prices.*.service_id.exists' => 'Dịch vụ của bảng giá không tồn tại.',
            'service_prices.*.price.required_with' => 'Giá dịch vụ là bắt buộc.',
            'service_prices.*.price.regex' => 'Số tiền không hợp lệ.',
            'service_prices.*.effective_from.date' => 'Ngày bắt đầu hiệu lực không hợp lệ.',
            'service_prices.*.effective_to.date' => 'Ngày hết hiệu lực không hợp lệ.',
            'service_prices.*.status.integer' => 'Trạng thái bảng giá dịch vụ không hợp lệ.',
            'service_prices.*.status.in' => 'Trạng thái bảng giá dịch vụ không nằm trong danh sách cho phép.',
            'delete_service_price_ids.array' => 'Danh sách bảng giá dịch vụ cần xóa không hợp lệ.',
            'delete_service_price_ids.max' => 'Mỗi lần chỉ được xóa tối đa 100 bảng giá dịch vụ.',
            'delete_service_price_ids.*.required' => 'ID bảng giá dịch vụ cần xóa là bắt buộc.',
            'delete_service_price_ids.*.integer' => 'ID bảng giá dịch vụ cần xóa không hợp lệ.',
            'delete_service_price_ids.*.distinct' => 'Danh sách bảng giá dịch vụ cần xóa không được trùng lặp.',
            'delete_service_price_ids.*.exists' => 'Bảng giá dịch vụ cần xóa không tồn tại trong tòa nhà này.',
            'setting_ids.array' => 'Danh sách cài đặt chọn sẵn không hợp lệ.',
            'setting_ids.max' => 'Mỗi lần chỉ được chọn tối đa 100 cài đặt mẫu.',
            'setting_ids.*.required' => 'ID cài đặt mẫu là bắt buộc.',
            'setting_ids.*.integer' => 'ID cài đặt mẫu không hợp lệ.',
            'setting_ids.*.distinct' => 'Danh sách cài đặt mẫu không được trùng lặp.',
            'setting_ids.*.exists' => 'Cài đặt mẫu không tồn tại hoặc đã thuộc tòa nhà khác.',
            'settings.array' => 'Danh sách cài đặt không hợp lệ.',
            'settings.max' => 'Mỗi tòa nhà chỉ được gửi tối đa 100 cài đặt mỗi lần.',
            'settings.*.id.integer' => 'ID cài đặt không hợp lệ.',
            'settings.*.id.distinct' => 'Danh sách cài đặt không được trùng ID.',
            'settings.*.id.exists' => 'Cài đặt không tồn tại trong tòa nhà này.',
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
            'delete_setting_ids.array' => 'Danh sách cài đặt cần xóa không hợp lệ.',
            'delete_setting_ids.max' => 'Mỗi lần chỉ được xóa tối đa 100 cài đặt.',
            'delete_setting_ids.*.required' => 'ID cài đặt cần xóa là bắt buộc.',
            'delete_setting_ids.*.integer' => 'ID cài đặt cần xóa không hợp lệ.',
            'delete_setting_ids.*.distinct' => 'Danh sách cài đặt cần xóa không được trùng lặp.',
            'delete_setting_ids.*.exists' => 'Cài đặt cần xóa không tồn tại trong tòa nhà này.',
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
