<?php

namespace App\Http\Requests\Admin\MaintenanceRequest;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'integer', 'in:1,2,3,4,5'],
            'note' => ['nullable', 'string', 'max:500'],
            'after_image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Trạng thái xử lý là bắt buộc.',
            'status.integer' => 'Trạng thái phải là số nguyên.',
            'status.in' => 'Trạng thái không hợp lệ (chỉ chấp nhận từ 1 đến 5).',
            'note.max' => 'Ghi chú không được vượt quá 500 ký tự.',
            'after_image.image' => 'Ảnh minh chứng phải là định dạng hình ảnh.',
            'after_image.mimes' => 'Ảnh minh chứng chỉ chấp nhận định dạng: jpeg, jpg, png, webp.',
            'after_image.max' => 'Ảnh minh chứng không được vượt quá 5MB.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
