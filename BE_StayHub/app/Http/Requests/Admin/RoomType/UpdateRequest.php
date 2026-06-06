<?php

namespace App\Http\Requests\Admin\RoomType;

use App\Helpers\ApiResponse;
use App\Models\RoomType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'required', 'integer', Rule::in(array_keys(RoomType::STATUS_LABELS))],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Tên loại phòng là bắt buộc khi cập nhật.',
            'name.string' => 'Tên loại phòng phải là chuỗi ký tự.',
            'name.max' => 'Tên loại phòng không được vượt quá 150 ký tự.',
            'description.string' => 'Mô tả loại phòng phải là chuỗi ký tự.',
            'status.required' => 'Trạng thái loại phòng là bắt buộc khi cập nhật.',
            'status.integer' => 'Trạng thái loại phòng không hợp lệ.',
            'status.in' => 'Trạng thái loại phòng không nằm trong danh sách cho phép.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
