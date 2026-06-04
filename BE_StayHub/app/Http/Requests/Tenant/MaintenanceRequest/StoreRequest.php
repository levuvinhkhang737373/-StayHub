<?php

namespace App\Http\Requests\Tenant\MaintenanceRequest;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'images' => ['nullable'],
            'images.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Tiêu đề yêu cầu sửa chữa là bắt buộc.',
            'title.string' => 'Tiêu đề phải là chuỗi ký tự.',
            'title.max' => 'Tiêu đề không được vượt quá 255 ký tự.',
            'description.required' => 'Mô tả chi tiết là bắt buộc.',
            'description.string' => 'Mô tả chi tiết phải là chuỗi ký tự.',
            'images.array' => 'Danh sách hình ảnh phải là một mảng.',
            'images.max' => 'Tối đa chỉ được gửi 5 ảnh.',
            'images.*.image' => 'Mỗi tệp tin gửi lên phải là hình ảnh.',
            'images.*.mimes' => 'Hình ảnh chỉ chấp nhận các định dạng: jpeg, jpg, png, webp.',
            'images.*.max' => 'Mỗi hình ảnh không được vượt quá 5MB.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
