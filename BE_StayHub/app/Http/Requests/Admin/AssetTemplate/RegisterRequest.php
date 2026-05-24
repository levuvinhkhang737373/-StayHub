<?php

namespace App\Http\Requests\Admin\AssetTemplate;

use App\Helpers\ApiResponse;
use App\Models\AssetTemplate;
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
            'default_unit_name' => ['nullable', 'integer', Rule::in(array_keys(AssetTemplate::UNIT_LABELS))],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'integer', Rule::in(array_keys(AssetTemplate::STATUS_LABELS))],
            'created_by' => ['nullable', 'integer', 'exists:admins,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Tên mẫu tài sản là bắt buộc.',
            'name.string' => 'Tên mẫu tài sản phải là chuỗi ký tự.',
            'name.max' => 'Tên mẫu tài sản không được vượt quá 150 ký tự.',
            'building_id.integer' => 'Tòa nhà của mẫu tài sản không hợp lệ.',
            'building_id.exists' => 'Tòa nhà của mẫu tài sản không tồn tại.',
            'default_unit_name.integer' => 'Đơn vị mặc định của mẫu tài sản không hợp lệ.',
            'default_unit_name.in' => 'Đơn vị mặc định của mẫu tài sản không nằm trong danh sách cho phép.',
            'description.string' => 'Mô tả mẫu tài sản phải là chuỗi ký tự.',
            'status.integer' => 'Trạng thái mẫu tài sản không hợp lệ.',
            'status.in' => 'Trạng thái mẫu tài sản không nằm trong danh sách cho phép.',
            'created_by.integer' => 'Người tạo mẫu tài sản không hợp lệ.',
            'created_by.exists' => 'Người tạo mẫu tài sản không tồn tại.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
