<?php

namespace App\Http\Requests\Admin\Service;

use App\Helpers\ApiResponse;
use App\Models\Service;
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
        $serviceId = $this->route('service');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'charge_method' => ['sometimes', 'required', 'integer', Rule::in(array_keys(Service::CHARGE_METHOD_LABELS))],
            'unit_name' => ['nullable', 'string', 'max:50'],
            'is_required' => ['sometimes', 'required', 'boolean'],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Tên dịch vụ là bắt buộc khi cập nhật.',
            'name.string' => 'Tên dịch vụ phải là chuỗi ký tự.',
            'name.max' => 'Tên dịch vụ không được vượt quá 150 ký tự.',
            'charge_method.required' => 'Phương thức tính phí là bắt buộc khi cập nhật.',
            'charge_method.integer' => 'Phương thức tính phí không hợp lệ.',
            'charge_method.in' => 'Phương thức tính phí không nằm trong danh sách cho phép.',
            'unit_name.string' => 'Đơn vị tính phải là chuỗi ký tự.',
            'unit_name.max' => 'Đơn vị tính không được vượt quá 50 ký tự.',
            'is_required.required' => 'Trạng thái bắt buộc của dịch vụ là bắt buộc khi cập nhật.',
            'is_required.boolean' => 'Trạng thái bắt buộc của dịch vụ không hợp lệ.',
            'is_active.required' => 'Trạng thái hoạt động của dịch vụ là bắt buộc khi cập nhật.',
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
