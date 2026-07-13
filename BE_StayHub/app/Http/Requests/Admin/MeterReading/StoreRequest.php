<?php

namespace App\Http\Requests\Admin\MeterReading;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'meter_device_id' => ['required', 'integer', 'exists:meter_devices,id'],
            'contract_id' => ['nullable', 'integer', 'exists:contracts,id'],
            'billing_month' => ['required', 'integer', 'min:1', 'max:12'],
            'billing_year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'current_reading' => ['required', 'numeric', 'min:0'],
            'reading_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
            'image_path' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'meter_device_id.required' => 'Vui lòng chọn đồng hồ.',
            'meter_device_id.integer' => 'Đồng hồ không hợp lệ.',
            'meter_device_id.exists' => 'Đồng hồ không tồn tại.',
            'contract_id.integer' => 'Hợp đồng không hợp lệ.',
            'contract_id.exists' => 'Hợp đồng không tồn tại.',
            'billing_month.required' => 'Vui lòng chọn tháng chốt.',
            'billing_month.integer' => 'Tháng chốt không hợp lệ.',
            'billing_month.min' => 'Tháng chốt không hợp lệ.',
            'billing_month.max' => 'Tháng chốt không hợp lệ.',
            'billing_year.required' => 'Vui lòng chọn năm chốt.',
            'billing_year.integer' => 'Năm chốt không hợp lệ.',
            'billing_year.min' => 'Năm chốt không hợp lệ.',
            'billing_year.max' => 'Năm chốt không hợp lệ.',
            'current_reading.required' => 'Vui lòng nhập chỉ số mới.',
            'current_reading.numeric' => 'Chỉ số mới phải là số.',
            'current_reading.min' => 'Chỉ số mới không được âm.',
            'reading_date.required' => 'Vui lòng chọn ngày chốt.',
            'reading_date.date' => 'Ngày chốt không hợp lệ.',
            'note.string' => 'Ghi chú phải là chuỗi.',
            'note.max' => 'Ghi chú tối đa 500 ký tự.',
            'image_path.string' => 'Đường dẫn ảnh không hợp lệ.',
            'image_path.max' => 'Đường dẫn ảnh tối đa 500 ký tự.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
