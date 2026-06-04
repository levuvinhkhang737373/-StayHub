<?php

namespace App\Http\Requests\Tenant\MaintenanceRequest;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class FeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'images' => ['nullable', 'array', 'max:3'],
            'images.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'rating.required' => 'Đánh giá số sao là bắt buộc.',
            'rating.integer' => 'Đánh giá phải là số nguyên.',
            'rating.min' => 'Đánh giá tối thiểu là 1 sao.',
            'rating.max' => 'Đánh giá tối đa là 5 sao.',
            'comment.max' => 'Nội dung phản hồi không được vượt quá 1000 ký tự.',
            'images.array' => 'Danh sách hình ảnh phản hồi phải là một mảng.',
            'images.max' => 'Tối đa chỉ được gửi 3 ảnh phản hồi.',
            'images.*.image' => 'Mỗi tệp tin gửi lên phải là hình ảnh.',
            'images.*.mimes' => 'Hình ảnh phản hồi chỉ chấp nhận định dạng: jpeg, jpg, png, webp.',
            'images.*.max' => 'Mỗi hình ảnh phản hồi không được vượt quá 5MB.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
