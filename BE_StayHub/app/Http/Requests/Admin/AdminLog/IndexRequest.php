<?php

namespace App\Http\Requests\Admin\AdminLog;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:255'],
            'admin_id' => ['nullable', 'integer', 'exists:admins,id'],
            'action' => ['nullable', 'string', 'max:100'],
            'entity_type' => ['nullable', 'string', 'max:100'],
            'entity_id' => ['nullable', 'integer', 'min:1'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'keyword.string' => 'Từ khóa tìm kiếm phải là chuỗi ký tự.',
            'keyword.max' => 'Từ khóa tìm kiếm không được vượt quá 255 ký tự.',
            'admin_id.integer' => 'Tài khoản admin phải là số nguyên.',
            'admin_id.exists' => 'Tài khoản admin không tồn tại.',
            'action.string' => 'Hành động phải là chuỗi ký tự.',
            'action.max' => 'Hành động không được vượt quá 100 ký tự.',
            'entity_type.string' => 'Loại đối tượng phải là chuỗi ký tự.',
            'entity_type.max' => 'Loại đối tượng không được vượt quá 100 ký tự.',
            'entity_id.integer' => 'Mã đối tượng phải là số nguyên.',
            'entity_id.min' => 'Mã đối tượng tối thiểu là 1.',
            'date_from.date_format' => 'Ngày bắt đầu phải có định dạng YYYY-MM-DD.',
            'date_to.date_format' => 'Ngày kết thúc phải có định dạng YYYY-MM-DD.',
            'date_to.after_or_equal' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.',
            'page.integer' => 'Trang hiện tại phải là số nguyên.',
            'page.min' => 'Trang hiện tại tối thiểu là 1.',
            'per_page.integer' => 'Số bản ghi mỗi trang phải là số nguyên.',
            'per_page.min' => 'Số bản ghi mỗi trang tối thiểu là 1.',
            'per_page.max' => 'Số bản ghi mỗi trang tối đa là 100.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
