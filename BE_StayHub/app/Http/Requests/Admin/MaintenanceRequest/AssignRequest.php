<?php

namespace App\Http\Requests\Admin\MaintenanceRequest;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class AssignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'assigned_to' => ['required', 'integer', 'exists:admins,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'assigned_to.required' => 'Nhân viên được phân công là bắt buộc.',
            'assigned_to.integer' => 'Mã nhân viên phải là số nguyên.',
            'assigned_to.exists' => 'Nhân viên được phân công không tồn tại trên hệ thống.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
