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
            'status' => ['required', 'integer', Rule::in([
                Contract::STATUS_ACTIVE,
                Contract::STATUS_CANCELLED,
            ])],
            'actual_end_date' => ['prohibited'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Trạng thái hợp đồng là bắt buộc.',
            'status.integer' => 'Trạng thái hợp đồng không hợp lệ.',
            'status.in' => 'Chỉ được kích hoạt hoặc hủy hợp đồng chờ ký. Hợp đồng hết hạn do hệ thống tự cập nhật, hợp đồng đang hiệu lực cần thanh lý bằng chức năng riêng.',
            'actual_end_date.prohibited' => 'Ngày kết thúc thực tế chỉ được nhập khi thanh lý hợp đồng.',
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
