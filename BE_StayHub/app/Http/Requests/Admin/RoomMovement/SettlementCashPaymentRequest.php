<?php

namespace App\Http\Requests\Admin\RoomMovement;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class SettlementCashPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:500'],
            'amount' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'note.string' => 'Ghi chú thanh toán tiền mặt phải là chuỗi ký tự.',
            'note.max' => 'Ghi chú thanh toán tiền mặt không được vượt quá 500 ký tự.',
            'amount.prohibited' => 'Số tiền thu chuyển phòng được hệ thống tự tính, không được gửi từ giao diện.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
