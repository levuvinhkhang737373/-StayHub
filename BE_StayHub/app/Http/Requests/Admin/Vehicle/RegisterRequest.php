<?php

namespace App\Http\Requests\Admin\Vehicle;

use App\Helpers\ApiResponse;
use App\Models\Vehicle;
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
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'vehicle_type' => ['required', 'integer', Rule::in(array_keys(Vehicle::VEHICLE_TYPE_LABELS))],
            'license_plate' => [
                Rule::requiredIf(fn () => in_array((int) $this->input('vehicle_type'), [Vehicle::VEHICLE_TYPE_MOTORBIKE, Vehicle::VEHICLE_TYPE_CAR])),
                'nullable',
                'string',
                'max:50',
            ],
            'brand' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'tenant_id.required' => 'Mã khách thuê là bắt buộc.',
            'tenant_id.integer' => 'Mã khách thuê không hợp lệ.',
            'tenant_id.exists' => 'Khách thuê không tồn tại.',
            'vehicle_type.required' => 'Loại xe là bắt buộc.',
            'vehicle_type.integer' => 'Loại xe không hợp lệ.',
            'vehicle_type.in' => 'Loại xe không nằm trong danh sách cho phép.',
            'license_plate.string' => 'Biển số xe phải là chuỗi ký tự.',
            'license_plate.max' => 'Biển số xe không được vượt quá 50 ký tự.',
            'brand.string' => 'Hiệu xe phải là chuỗi ký tự.',
            'brand.max' => 'Hiệu xe không được vượt quá 100 ký tự.',
            'color.string' => 'Màu xe phải là chuỗi ký tự.',
            'color.max' => 'Màu xe không được vượt quá 50 ký tự.',
            'is_active.boolean' => 'Trạng thái hoạt động không hợp lệ.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
