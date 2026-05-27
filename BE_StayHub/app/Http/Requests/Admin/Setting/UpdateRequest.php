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
            'setting_name' => ['sometimes', 'required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_.-]+$/'],
            'setting_value' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_public' => ['sometimes', 'required', 'boolean'],
            'display_order' => ['sometimes', 'required', 'integer', 'min:0', 'max:999999'],
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
            'setting_name.required' => 'Khóa cài đặt là bắt buộc khi cập nhật.',
            'setting_name.string' => 'Khóa cài đặt phải là chuỗi ký tự.',
            'setting_name.max' => 'Khóa cài đặt không được vượt quá 255 ký tự.',
            'setting_name.regex' => 'Khóa cài đặt chỉ được chứa chữ, số, dấu gạch dưới, gạch ngang hoặc dấu chấm.',
            'setting_value.string' => 'Giá trị cài đặt phải là chuỗi ký tự.',
            'setting_value.max' => 'Giá trị cài đặt không được vượt quá 500 ký tự.',
            'description.string' => 'Mô tả cài đặt phải là chuỗi ký tự.',
            'description.max' => 'Mô tả cài đặt không được vượt quá 500 ký tự.',
            'is_public.required' => 'Trạng thái hiển thị của cài đặt là bắt buộc khi cập nhật.',
            'is_public.boolean' => 'Trạng thái hiển thị của cài đặt không hợp lệ.',
            'display_order.required' => 'Thứ tự hiển thị là bắt buộc khi cập nhật.',
            'display_order.integer' => 'Thứ tự hiển thị phải là số nguyên.',
            'display_order.min' => 'Thứ tự hiển thị không được nhỏ hơn 0.',
            'display_order.max' => 'Thứ tự hiển thị không được vượt quá 999999.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
