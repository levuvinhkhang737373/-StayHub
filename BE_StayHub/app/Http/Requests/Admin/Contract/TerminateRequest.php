<?php

namespace App\Http\Requests\Admin\Contract;

use App\Helpers\ApiResponse;
use App\Models\ContractDepositTransaction;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class TerminateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'actual_end_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'deduction_amount' => ['nullable', 'regex:/^\d{1,13}(\.\d{1,2})?$/'],
            'payment_method' => ['nullable', 'integer', Rule::in([
                ContractDepositTransaction::PAYMENT_METHOD_CASH,
                ContractDepositTransaction::PAYMENT_METHOD_BANK_TRANSFER,
            ])],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'actual_end_date.required' => 'Ngày thanh lý hợp đồng là bắt buộc.',
            'actual_end_date.date_format' => 'Ngày thanh lý hợp đồng phải đúng định dạng YYYY-MM-DD.',
            'actual_end_date.before_or_equal' => 'Ngày thanh lý hợp đồng không được ở tương lai.',
            'deduction_amount.regex' => 'Số tiền cấn trừ cọc phải là số tiền hợp lệ, không âm và tối đa 2 chữ số thập phân.',
            'payment_method.integer' => 'Phương thức hoàn cọc không hợp lệ.',
            'payment_method.in' => 'Phương thức hoàn cọc phải là Tiền mặt hoặc Chuyển khoản.',
            'note.string' => 'Ghi chú thanh lý phải là chuỗi ký tự.',
            'note.max' => 'Ghi chú thanh lý không được vượt quá 2000 ký tự.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
