<?php

namespace App\Http\Requests\Admin\ExpenseCategory;

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
        $expenseCategoryId = $this->route('expenseCategory');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:150', Rule::unique('expense_categories', 'name')->ignore($expenseCategoryId)],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'required', 'boolean'],
            'status' => ['sometimes', 'required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Tên danh mục chi phí là bắt buộc khi cập nhật.',
            'name.string' => 'Tên danh mục chi phí phải là chuỗi ký tự.',
            'name.max' => 'Tên danh mục chi phí không được vượt quá 150 ký tự.',
            'name.unique' => 'Tên danh mục chi phí đã tồn tại.',
            'description.string' => 'Mô tả danh mục chi phí phải là chuỗi ký tự.',
            'description.max' => 'Mô tả danh mục chi phí không được vượt quá 2000 ký tự.',
            'is_active.required' => 'Trạng thái hoạt động của danh mục chi phí là bắt buộc khi cập nhật.',
            'is_active.boolean' => 'Trạng thái hoạt động của danh mục chi phí không hợp lệ.',
            'status.required' => 'Trạng thái danh mục chi phí là bắt buộc khi cập nhật.',
            'status.boolean' => 'Trạng thái danh mục chi phí không hợp lệ.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
