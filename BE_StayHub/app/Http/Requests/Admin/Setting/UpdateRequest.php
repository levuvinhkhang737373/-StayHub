<?php

namespace App\Http\Requests\Admin\Setting;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'building_id' => ['nullable', 'integer', 'exists:buildings,id'],
            'setting_label' => ['sometimes', 'required', 'string', 'max:150'],
            'setting_value' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_public' => ['sometimes', 'required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'building_id.integer' => 'Tòa nhà của cài đặt không hợp lệ.',
            'building_id.exists' => 'Tòa nhà của cài đặt không tồn tại.',
            'setting_label.required' => 'Tên hiển thị cài đặt là bắt buộc khi cập nhật.',
            'setting_label.string' => 'Tên hiển thị cài đặt phải là chuỗi ký tự.',
            'setting_label.max' => 'Tên hiển thị cài đặt không được vượt quá 150 ký tự.',
            'setting_value.string' => 'Giá trị cài đặt phải là chuỗi ký tự.',
            'setting_value.max' => 'Giá trị cài đặt không được vượt quá 500 ký tự.',
            'description.string' => 'Mô tả cài đặt phải là chuỗi ký tự.',
            'description.max' => 'Mô tả cài đặt không được vượt quá 500 ký tự.',
            'is_public.required' => 'Trạng thái hiển thị của cài đặt là bắt buộc khi cập nhật.',
            'is_public.boolean' => 'Trạng thái hiển thị của cài đặt không hợp lệ.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
