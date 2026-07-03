<?php

namespace App\Http\Requests\Admin\SecurityCamera;

use App\Helpers\ApiResponse;
use App\Models\SecurityCamera;
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
            'building_id' => ['nullable', 'integer', 'exists:buildings,id'],
            'source_type' => ['nullable', 'integer', Rule::in(array_keys(SecurityCamera::SOURCE_TYPE_LABELS))],
            'status' => ['nullable', 'integer', Rule::in(array_keys(SecurityCamera::STATUS_LABELS))],
            'is_ai_enabled' => ['nullable', 'boolean'],
            'keyword' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'building_id.integer' => 'Mã tòa nhà không hợp lệ.',
            'building_id.exists' => 'Tòa nhà không tồn tại.',
            'source_type.integer' => 'Loại nguồn camera không hợp lệ.',
            'source_type.in' => 'Loại nguồn camera không nằm trong danh sách cho phép.',
            'status.integer' => 'Trạng thái camera không hợp lệ.',
            'status.in' => 'Trạng thái camera không nằm trong danh sách cho phép.',
            'is_ai_enabled.boolean' => 'Trạng thái giám sát AI 24/24 không hợp lệ.',
            'keyword.string' => 'Từ khóa tìm kiếm phải là chuỗi ký tự.',
            'keyword.max' => 'Từ khóa tìm kiếm không được vượt quá 100 ký tự.',
            'page.integer' => 'Số trang không hợp lệ.',
            'page.min' => 'Số trang tối thiểu là 1.',
            'per_page.integer' => 'Số lượng bản ghi mỗi trang không hợp lệ.',
            'per_page.min' => 'Số lượng bản ghi mỗi trang tối thiểu là 1.',
            'per_page.max' => 'Số lượng bản ghi mỗi trang tối đa là 100.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
