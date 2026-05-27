<?php

namespace App\Http\Requests\Admin\AdminAccount;

use App\Helpers\ApiResponse;
use App\Models\Admin;
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
            'role' => ['nullable', 'integer', Rule::in(array_keys(Admin::ROLE_LABELS))],
            'status' => ['nullable', 'integer', Rule::in(array_keys(Admin::STATUS_LABELS))],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'keyword.string' => 'Từ khóa tìm kiếm phải là chuỗi ký tự.',
            'keyword.max' => 'Từ khóa tìm kiếm không được vượt quá 255 ký tự.',
            'role.integer' => 'Vai trò tài khoản admin không hợp lệ.',
            'role.in' => 'Vai trò tài khoản admin không nằm trong danh sách cho phép.',
            'status.integer' => 'Trạng thái tài khoản admin không hợp lệ.',
            'status.in' => 'Trạng thái tài khoản admin không nằm trong danh sách cho phép.',
            'page.integer' => 'Trang hiện tại phải là số nguyên.',
            'page.min' => 'Trang hiện tại tối thiểu là 1.',
            'per_page.integer' => 'Số bản ghi mỗi trang phải là số nguyên.',
            'per_page.min' => 'Số bản ghi mỗi trang tối thiểu là 1.',
            'per_page.max' => 'Số bản ghi mỗi trang tối đa là 100.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
