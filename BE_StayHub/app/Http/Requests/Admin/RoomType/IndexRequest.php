<?php

namespace App\Http\Requests\Admin\RoomType;

use App\Helpers\ApiResponse;
use App\Models\RoomType;
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
            'created_by_me' => ['nullable', 'boolean'],
            'status' => ['nullable', 'integer', Rule::in(array_keys(RoomType::STATUS_LABELS))],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'keyword.string' => 'Từ khóa tìm kiếm loại phòng phải là chuỗi ký tự.',
            'keyword.max' => 'Từ khóa tìm kiếm loại phòng không được vượt quá 255 ký tự.',
            'only_global.boolean' => 'Bộ lọc loại phòng mẫu không hợp lệ.',
            'created_by_me.boolean' => 'Bộ lọc loại phòng theo người tạo không hợp lệ.',
            'status.integer' => 'Trạng thái loại phòng không hợp lệ.',
            'status.in' => 'Trạng thái loại phòng không nằm trong danh sách cho phép.',
            'per_page.integer' => 'Số dòng mỗi trang phải là số nguyên.',
            'per_page.min' => 'Số dòng mỗi trang phải lớn hơn hoặc bằng 1.',
            'per_page.max' => 'Số dòng mỗi trang không được vượt quá 1000.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
