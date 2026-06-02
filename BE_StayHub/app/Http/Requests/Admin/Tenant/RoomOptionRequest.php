<?php

namespace App\Http\Requests\Admin\Tenant;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class RoomOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:255'],
            'building_id' => ['nullable', 'integer', Rule::exists('buildings', 'id')],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'keyword.string' => 'Từ khóa tìm phòng phải là chuỗi ký tự.',
            'keyword.max' => 'Từ khóa tìm phòng không được vượt quá 255 ký tự.',
            'building_id.integer' => 'Tòa nhà không hợp lệ.',
            'building_id.exists' => 'Tòa nhà không tồn tại.',
            'limit.integer' => 'Số lượng phòng cần lấy phải là số nguyên.',
            'limit.min' => 'Số lượng phòng cần lấy tối thiểu là 1.',
            'limit.max' => 'Số lượng phòng cần lấy tối đa là 200.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
