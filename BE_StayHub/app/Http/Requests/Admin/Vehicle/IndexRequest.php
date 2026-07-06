<?php

namespace App\Http\Requests\Admin\Vehicle;

use App\Helpers\ApiResponse;
use App\Models\Vehicle;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'vehicle_type' => ['nullable', 'integer', Rule::in(array_keys(Vehicle::VEHICLE_TYPE_LABELS))],
            'license_plate' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
            'without_active_contract' => ['nullable', 'boolean'],
            'without_reserved_contract' => ['nullable', 'boolean'],
            'keyword' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'tenant_id.integer' => 'Mã khách thuê không hợp lệ.',
            'tenant_id.exists' => 'Khách thuê không tồn tại.',
            'vehicle_type.integer' => 'Loại xe không hợp lệ.',
            'vehicle_type.in' => 'Loại xe không nằm trong danh sách cho phép.',
            'license_plate.string' => 'Biển số xe phải là chuỗi ký tự.',
            'license_plate.max' => 'Biển số xe không được vượt quá 50 ký tự.',
            'is_active.boolean' => 'Trạng thái hoạt động không hợp lệ.',
            'keyword.string' => 'Từ khóa tìm kiếm phải là chuỗi ký tự.',
            'keyword.max' => 'Từ khóa tìm kiếm không được vượt quá 100 ký tự.',
            'per_page.integer' => 'Số lượng bản ghi mỗi trang không hợp lệ.',
            'per_page.min' => 'Số lượng bản ghi mỗi trang tối thiểu là 1.',
            'per_page.max' => 'Số lượng bản ghi mỗi trang tối đa là 100.',
            'page.integer' => 'Trang hiện tại không hợp lệ.',
            'page.min' => 'Trang hiện tại tối thiểu là 1.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
