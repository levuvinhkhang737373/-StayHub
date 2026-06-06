<?php

namespace App\Http\Requests\Admin\AssetTemplate;

use App\Helpers\ApiResponse;
use App\Models\AssetTemplate;
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
            'only_global' => ['nullable', 'boolean'],
            'default_unit_name' => ['nullable', 'integer', Rule::in(array_keys(AssetTemplate::UNIT_LABELS))],
            'status' => ['nullable', 'integer', Rule::in(array_keys(AssetTemplate::STATUS_LABELS))],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'keyword.string' => 'Từ khóa tìm kiếm mẫu tài sản phải là chuỗi ký tự.',
            'keyword.max' => 'Từ khóa tìm kiếm mẫu tài sản không được vượt quá 255 ký tự.',
            'only_global.boolean' => 'Bộ lọc mẫu tài sản dùng chung không hợp lệ.',
            'default_unit_name.integer' => 'Đơn vị mặc định của mẫu tài sản không hợp lệ.',
            'default_unit_name.in' => 'Đơn vị mặc định của mẫu tài sản không nằm trong danh sách cho phép.',
            'status.integer' => 'Trạng thái mẫu tài sản không hợp lệ.',
            'status.in' => 'Trạng thái mẫu tài sản không nằm trong danh sách cho phép.',
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
