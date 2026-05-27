<?php

namespace App\Http\Requests\Admin\Service;

use App\Helpers\ApiResponse;
use App\Models\Service;
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
            'service_code' => ['required', 'string', 'max:50', Rule::unique('services', 'service_code')],
            'name' => ['required', 'string', 'max:150'],
            'service_type' => ['required', 'string', Rule::in(array_keys(Service::SERVICE_TYPE_LABELS))],
            'charge_method' => ['required', 'integer', Rule::in(array_keys(Service::CHARGE_METHOD_LABELS))],
            'unit_name' => ['nullable', 'string', 'max:50'],
            'is_required' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'service_code.required' => 'Mã dịch vụ là bắt buộc.',
            'service_code.string' => 'Mã dịch vụ phải là chuỗi ký tự.',
            'service_code.max' => 'Mã dịch vụ không được vượt quá 50 ký tự.',
            'service_code.unique' => 'Mã dịch vụ đã tồn tại.',
            'name.required' => 'Tên dịch vụ là bắt buộc.',
            'name.string' => 'Tên dịch vụ phải là chuỗi ký tự.',
            'name.max' => 'Tên dịch vụ không được vượt quá 150 ký tự.',
            'service_type.required' => 'Loại dịch vụ là bắt buộc.',
            'service_type.string' => 'Loại dịch vụ không hợp lệ.',
            'service_type.in' => 'Loại dịch vụ không nằm trong danh sách cho phép.',
            'charge_method.required' => 'Phương thức tính phí là bắt buộc.',
            'charge_method.integer' => 'Phương thức tính phí không hợp lệ.',
            'charge_method.in' => 'Phương thức tính phí không nằm trong danh sách cho phép.',
            'unit_name.string' => 'Đơn vị tính phải là chuỗi ký tự.',
            'unit_name.max' => 'Đơn vị tính không được vượt quá 50 ký tự.',
            'is_required.boolean' => 'Trạng thái bắt buộc của dịch vụ không hợp lệ.',
            'is_active.boolean' => 'Trạng thái hoạt động của dịch vụ không hợp lệ.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
