<?php

namespace App\Http\Requests\Admin\Building;

use App\Helpers\ApiResponse;
use App\Models\Building;
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
            'region_id' => ['nullable', 'integer', 'exists:regions,id'],
            'manager_admin_id' => ['nullable', 'integer', 'exists:admins,id'],
            'gender_policy' => ['nullable', 'integer', Rule::in(array_keys(Building::GENDER_POLICY_LABELS))],
            'status' => ['nullable', 'integer', Rule::in(array_keys(Building::STATUS_LABELS))],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'keyword.string' => 'Từ khóa tìm kiếm tòa nhà phải là chuỗi ký tự.',
            'keyword.max' => 'Từ khóa tìm kiếm tòa nhà không được vượt quá 255 ký tự.',
            'region_id.integer' => 'Khu vực của tòa nhà không hợp lệ.',
            'region_id.exists' => 'Khu vực của tòa nhà không tồn tại.',
            'manager_admin_id.integer' => 'Quản lý tòa nhà không hợp lệ.',
            'manager_admin_id.exists' => 'Quản lý tòa nhà không tồn tại.',
            'gender_policy.integer' => 'Chính sách giới tính của tòa nhà không hợp lệ.',
            'gender_policy.in' => 'Chính sách giới tính của tòa nhà không nằm trong danh sách cho phép.',
            'status.integer' => 'Trạng thái tòa nhà không hợp lệ.',
            'status.in' => 'Trạng thái tòa nhà không nằm trong danh sách cho phép.',
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
