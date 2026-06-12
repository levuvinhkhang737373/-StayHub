<?php

namespace App\Http\Requests\Admin\Meter;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'integer', Rule::in([1, 2, 3, 4])],
            'replaced_by_meter_id' => ['nullable', 'integer', 'exists:meter_devices,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Trạng thái là bắt buộc.',
            'status.integer' => 'Trạng thái không hợp lệ.',
            'status.in' => 'Trạng thái không nằm trong danh sách cho phép.',
            'replaced_by_meter_id.integer' => 'ID đồng hồ thay thế không hợp lệ.',
            'replaced_by_meter_id.exists' => 'Đồng hồ thay thế không tồn tại.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
