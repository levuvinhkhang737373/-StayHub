<?php

namespace App\Http\Requests\Admin\RoomType;

use App\Helpers\ApiResponse;
use App\Models\RoomType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'building_id' => ['nullable', 'integer', 'exists:buildings,id'],
            'default_price' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'integer', Rule::in(array_keys(RoomType::STATUS_LABELS))],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Tên loại phòng là bắt buộc.',
            'name.string' => 'Tên loại phòng phải là chuỗi ký tự.',
            'name.max' => 'Tên loại phòng không được vượt quá 150 ký tự.',
            'building_id.integer' => 'Tòa nhà của loại phòng không hợp lệ.',
            'building_id.exists' => 'Tòa nhà của loại phòng không tồn tại.',
            'default_price.required' => 'Giá mặc định của loại phòng là bắt buộc.',
            'default_price.numeric' => 'Giá mặc định của loại phòng phải là số.',
            'default_price.min' => 'Giá mặc định của loại phòng không được nhỏ hơn 0.',
            'description.string' => 'Mô tả loại phòng phải là chuỗi ký tự.',
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
