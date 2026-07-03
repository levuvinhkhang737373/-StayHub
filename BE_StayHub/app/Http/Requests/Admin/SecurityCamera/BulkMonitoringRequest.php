<?php

namespace App\Http\Requests\Admin\SecurityCamera;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class BulkMonitoringRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'building_id' => ['nullable', 'integer', 'exists:buildings,id'],
            'keyword' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'enabled.required' => 'Vui lòng chọn trạng thái giám sát 24/24.',
            'enabled.boolean' => 'Trạng thái giám sát 24/24 không hợp lệ.',
            'building_id.integer' => 'Mã tòa nhà không hợp lệ.',
            'building_id.exists' => 'Tòa nhà không tồn tại.',
            'keyword.string' => 'Từ khóa tìm kiếm phải là chuỗi ký tự.',
            'keyword.max' => 'Từ khóa tìm kiếm không được vượt quá 100 ký tự.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
