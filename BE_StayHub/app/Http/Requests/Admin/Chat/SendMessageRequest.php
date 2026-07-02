<?php

namespace App\Http\Requests\Admin\Chat;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['required_without:images', 'string', 'max:5000', 'nullable'],
            'images' => ['nullable', 'array', 'max:5'],
            'images.*' => ['image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required_without' => 'Vui lòng nhập nội dung hoặc đính kèm ảnh.',
            'body.string' => 'Nội dung tin nhắn phải là chuỗi ký tự.',
            'body.max' => 'Nội dung tin nhắn không được vượt quá 5000 ký tự.',
            'images.max' => 'Bạn chỉ được gửi tối đa 5 ảnh cùng lúc.',
            'images.*.image' => 'File đính kèm phải là ảnh.',
            'images.*.mimes' => 'Ảnh phải có định dạng: jpeg, png, jpg, webp.',
            'images.*.max' => 'Kích thước mỗi ảnh không được vượt quá 5MB.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
