<?php

namespace App\Http\Requests\Admin\AdminAccount;

use App\Helpers\ApiResponse;
use App\Models\Admin;
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
            'status' => ['required', 'integer', Rule::in(array_keys(Admin::STATUS_LABELS))],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Trạng thái tài khoản admin là bắt buộc.',
            'status.integer' => 'Trạng thái tài khoản admin không hợp lệ.',
            'status.in' => 'Trạng thái tài khoản admin không nằm trong danh sách cho phép.',
            'reason.string' => 'Lý do đổi trạng thái phải là chuỗi ký tự.',
            'reason.max' => 'Lý do đổi trạng thái không được vượt quá 500 ký tự.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
