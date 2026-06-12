<?php

namespace App\Http\Requests\Admin\Contract;

use App\Helpers\ApiResponse;
use App\Models\Contract;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'integer', Rule::in(array_keys(Contract::STATUS_LABELS))],
            'actual_end_date' => ['nullable', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Trạng thái hợp đồng là bắt buộc.',
            'status.integer' => 'Trạng thái hợp đồng không hợp lệ.',
            'status.in' => 'Trạng thái hợp đồng không nằm trong danh sách cho phép.',
            'actual_end_date.date_format' => 'Ngày kết thúc thực tế phải đúng định dạng YYYY-MM-DD.',
            'note.string' => 'Ghi chú đổi trạng thái hợp đồng phải là chuỗi ký tự.',
            'note.max' => 'Ghi chú đổi trạng thái hợp đồng không được vượt quá 1000 ký tự.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
