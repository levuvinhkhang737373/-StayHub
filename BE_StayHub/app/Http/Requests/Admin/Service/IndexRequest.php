<?php

namespace App\Http\Requests\Admin\Service;

use App\Helpers\ApiResponse;
use App\Models\Service;
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
            'keyword' => ['nullable', 'string', 'max:255'],
            'service_type' => ['nullable', 'string', Rule::in(array_keys(Service::SERVICE_TYPE_LABELS))],
            'charge_method' => ['nullable', 'integer', Rule::in(array_keys(Service::CHARGE_METHOD_LABELS))],
            'is_required' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'keyword.string' => 'Từ khóa tìm kiếm dịch vụ phải là chuỗi ký tự.',
            'keyword.max' => 'Từ khóa tìm kiếm dịch vụ không được vượt quá 255 ký tự.',
            'service_type.string' => 'Loại dịch vụ không hợp lệ.',
            'service_type.in' => 'Loại dịch vụ không nằm trong danh sách cho phép.',
            'charge_method.integer' => 'Phương thức tính phí không hợp lệ.',
            'charge_method.in' => 'Phương thức tính phí không nằm trong danh sách cho phép.',
            'is_required.boolean' => 'Trạng thái bắt buộc của dịch vụ không hợp lệ.',
            'is_active.boolean' => 'Trạng thái hoạt động của dịch vụ không hợp lệ.',
            'per_page.integer' => 'Số dòng mỗi trang phải là số nguyên.',
            'per_page.min' => 'Số dòng mỗi trang phải lớn hơn hoặc bằng 1.',
            'per_page.max' => 'Số dòng mỗi trang không được vượt quá 100.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
