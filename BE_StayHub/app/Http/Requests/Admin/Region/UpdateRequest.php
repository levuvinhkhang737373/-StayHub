<?php

namespace App\Http\Requests\Admin\Region;

use App\Helpers\ApiResponse;
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
            'parent_id' => ['nullable', 'integer', 'exists:regions,id'],
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('regions', 'code')->ignore($this->route('region'))],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'path' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('regions', 'slug')->ignore($this->route('region'))],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'status' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'parent_id.integer' => 'Khu vực cha không hợp lệ.',
            'parent_id.exists' => 'Khu vực cha không tồn tại.',
            'code.required' => 'Mã khu vực là bắt buộc khi cập nhật.',
            'code.string' => 'Mã khu vực phải là chuỗi ký tự.',
            'code.max' => 'Mã khu vực không được vượt quá 50 ký tự.',
            'code.unique' => 'Mã khu vực đã tồn tại.',
            'name.required' => 'Tên khu vực là bắt buộc khi cập nhật.',
            'name.string' => 'Tên khu vực phải là chuỗi ký tự.',
            'name.max' => 'Tên khu vực không được vượt quá 150 ký tự.',
            'path.string' => 'Đường dẫn khu vực phải là chuỗi ký tự.',
            'path.max' => 'Đường dẫn khu vực không được vượt quá 255 ký tự.',
            'slug.string' => 'Slug khu vực phải là chuỗi ký tự.',
            'slug.max' => 'Slug khu vực không được vượt quá 255 ký tự.',
            'slug.unique' => 'Slug khu vực đã tồn tại.',
            'description.string' => 'Mô tả khu vực phải là chuỗi ký tự.',
            'is_active.boolean' => 'Trạng thái hoạt động không hợp lệ.',
            'status.boolean' => 'Trạng thái khu vực không hợp lệ.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
