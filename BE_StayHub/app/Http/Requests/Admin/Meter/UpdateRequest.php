<?php

namespace App\Http\Requests\Admin\Meter;

use App\Helpers\ApiResponse;
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
        $meterDevice = $this->route('meter_device');

        return [
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'room_number' => ['nullable', 'string', 'max:50'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'meter_code' => ['nullable', 'string', 'max:100', 'unique:meter_devices,meter_code,' . $meterDevice],
            'meter_type' => ['nullable', 'integer', Rule::in([1, 2])],
            'initial_reading' => ['nullable', 'numeric', 'min:0'],
            'installed_at' => ['nullable', 'date'],
            'final_reading' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'integer', Rule::in([1, 2, 3, 4])],
            'replaced_by_meter_id' => ['nullable', 'integer', 'exists:meter_devices,id'],
            'note' => ['nullable', 'string', 'max:500'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:5120'],
            'delete_image' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'room_id.integer' => 'ID phòng không hợp lệ.',
            'room_id.exists' => 'Phòng không tồn tại.',
            'room_number.string' => 'Số phòng không hợp lệ.',
            'room_number.max' => 'Số phòng tối đa 50 ký tự.',
            'service_id.integer' => 'ID dịch vụ không hợp lệ.',
            'service_id.exists' => 'Dịch vụ không tồn tại.',
            'meter_code.max' => 'Mã đồng hồ tối đa 100 ký tự.',
            'meter_code.unique' => 'Mã đồng hồ đã tồn tại.',
            'meter_type.integer' => 'Loại đồng hồ không hợp lệ.',
            'meter_type.in' => 'Loại đồng hồ không nằm trong danh sách cho phép.',
            'initial_reading.numeric' => 'Chỉ số khởi tạo phải là số.',
            'initial_reading.min' => 'Chỉ số khởi tạo không được âm.',
            'installed_at.date' => 'Ngày lắp không hợp lệ.',
            'final_reading.numeric' => 'Chỉ số cuối phải là số.',
            'final_reading.min' => 'Chỉ số cuối không được âm.',
            'status.integer' => 'Trạng thái không hợp lệ.',
            'status.in' => 'Trạng thái không nằm trong danh sách cho phép.',
            'replaced_by_meter_id.integer' => 'ID đồng hồ thay thế không hợp lệ.',
            'replaced_by_meter_id.exists' => 'Đồng hồ thay thế không tồn tại.',
            'note.string' => 'Ghi chú phải là chuỗi.',
            'note.max' => 'Ghi chú tối đa 500 ký tự.',
            'image.image' => 'Tệp tải lên phải là hình ảnh.',
            'image.mimes' => 'Hình ảnh phải có định dạng: jpeg, png, jpg, gif.',
            'image.max' => 'Kích thước hình ảnh tối đa 5MB.',
            'delete_image.boolean' => 'Yêu cầu xóa hình ảnh không hợp lệ.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
