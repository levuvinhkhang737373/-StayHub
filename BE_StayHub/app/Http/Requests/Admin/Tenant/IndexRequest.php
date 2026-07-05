<?php

namespace App\Http\Requests\Admin\Tenant;

use App\Helpers\ApiResponse;
use App\Models\Tenant;
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
            'status' => ['nullable', 'integer', Rule::in(array_keys(Tenant::STATUS_LABELS))],
            'gender' => ['nullable', 'integer', Rule::in(array_keys(Tenant::GENDER_LABELS))],
            'identity_type' => ['nullable', 'integer', Rule::in(array_keys(Tenant::IDENTITY_TYPE_LABELS))],
            'building_id' => ['nullable', 'integer', Rule::exists('buildings', 'id')],
            'created_by' => ['nullable', 'integer', Rule::exists('admins', 'id')],
            'without_active_contract' => ['nullable', 'boolean'],
            'without_reserved_contract' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'keyword.string' => 'Từ khóa tìm kiếm khách thuê phải là chuỗi ký tự.',
            'keyword.max' => 'Từ khóa tìm kiếm khách thuê không được vượt quá 255 ký tự.',
            'status.integer' => 'Trạng thái khách thuê không hợp lệ.',
            'status.in' => 'Trạng thái khách thuê không nằm trong danh sách cho phép.',
            'gender.integer' => 'Giới tính khách thuê không hợp lệ.',
            'gender.in' => 'Giới tính khách thuê không nằm trong danh sách cho phép.',
            'identity_type.integer' => 'Loại giấy tờ khách thuê không hợp lệ.',
            'identity_type.in' => 'Loại giấy tờ khách thuê không nằm trong danh sách cho phép.',
            'created_by.integer' => 'Người tạo khách thuê không hợp lệ.',
            'created_by.exists' => 'Người tạo khách thuê không tồn tại.',
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
