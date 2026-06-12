<?php

namespace App\Http\Requests\Admin\Contract;

use App\Helpers\ApiResponse;
use App\Models\ContractDepositTransaction;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class DepositTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'transaction_type' => [
                'required', 
                'integer', 
                Rule::in([
                    ContractDepositTransaction::TRANSACTION_TYPE_COLLECT,
                    ContractDepositTransaction::TRANSACTION_TYPE_REFUND,
                    ContractDepositTransaction::TRANSACTION_TYPE_DEDUCT,
                ])
            ],
            'amount' => ['required', 'regex:/^\d{1,13}(\.\d{1,2})?$/'],
            'transaction_date' => ['required', 'date_format:Y-m-d'],
            'payment_method' => [
                'required', 
                'integer', 
                Rule::in([
                    ContractDepositTransaction::PAYMENT_METHOD_CASH,
                    ContractDepositTransaction::PAYMENT_METHOD_BANK_TRANSFER,
                ])
            ],
            'note' => ['nullable', 'string', 'max:2000'],
            'transaction_reference' => ['nullable', 'string', 'max:150', Rule::unique('contract_deposit_transactions', 'transaction_reference')],
        ];
    }

    public function messages(): array
    {
        return [
            'transaction_type.required' => 'Loại giao dịch cọc là bắt buộc.',
            'transaction_type.integer' => 'Loại giao dịch cọc không hợp lệ.',
            'transaction_type.in' => 'Loại giao dịch cọc phải là Thu cọc, Hoàn cọc hoặc Khấu trừ cọc.',
            'amount.required' => 'Số tiền giao dịch cọc là bắt buộc.',
            'amount.regex' => 'Số tiền phải là số tiền hợp lệ, không âm và tối đa 2 chữ số thập phân.',
            'transaction_date.required' => 'Ngày giao dịch cọc là bắt buộc.',
            'transaction_date.date_format' => 'Ngày giao dịch phải đúng định dạng YYYY-MM-DD.',
            'payment_method.required' => 'Phương thức thanh toán là bắt buộc.',
            'payment_method.integer' => 'Phương thức thanh toán không hợp lệ.',
            'payment_method.in' => 'Phương thức thanh toán phải là Tiền mặt hoặc Chuyển khoản.',
            'note.string' => 'Ghi chú phải là chuỗi ký tự.',
            'note.max' => 'Ghi chú không được vượt quá 2000 ký tự.',
            'transaction_reference.string' => 'Mã tham chiếu giao dịch phải là chuỗi ký tự.',
            'transaction_reference.max' => 'Mã tham chiếu giao dịch không được vượt quá 150 ký tự.',
            'transaction_reference.unique' => 'Mã tham chiếu giao dịch đã tồn tại trên hệ thống.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
