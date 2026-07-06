<?php

namespace App\Http\Requests\Admin\Setting;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

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
            'building_id' => ['nullable', 'integer', 'exists:buildings,id'],
            'only_global' => ['nullable', 'boolean'],
            'is_public' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'keyword.string' => 'Từ khóa tìm kiếm cài đặt phải là chuỗi ký tự.',
            'keyword.max' => 'Từ khóa tìm kiếm cài đặt không được vượt quá 255 ký tự.',
            'building_id.integer' => 'Tòa nhà của cài đặt không hợp lệ.',
            'building_id.exists' => 'Tòa nhà của cài đặt không tồn tại.',
            'only_global.boolean' => 'Bộ lọc cài đặt dùng chung không hợp lệ.',
            'is_public.boolean' => 'Trạng thái hiển thị của cài đặt không hợp lệ.',
            'per_page.integer' => 'Số dòng mỗi trang phải là số nguyên.',
            'per_page.min' => 'Số dòng mỗi trang phải lớn hơn hoặc bằng 1.',
            'per_page.max' => 'Số dòng mỗi trang không được vượt quá 100.',
            'page.integer' => 'Trang hiện tại không hợp lệ.',
            'page.min' => 'Trang hiện tại tối thiểu là 1.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
