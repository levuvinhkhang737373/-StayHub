<?php

namespace App\Http\Requests\Admin\Invoice;

use App\Helpers\AdminScope;
use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class RecordPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = $this->user('admin');
        if (! $admin) {
            return false;
        }

        return AdminScope::isSuperAdmin($admin) || AdminScope::isBuildingManager($admin);
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, 'Bạn không có quyền ghi nhận thanh toán', 403, null, 403)
        );
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'payment_date' => ['nullable', 'date'],
            'payment_method' => ['required', 'integer', 'in:1,2'],
            'transaction_reference' => ['nullable', 'string', 'max:150'],
            'note' => ['nullable', 'string', 'max:500'],
            'proof_image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute là bắt buộc.',
            'required_with' => ':attribute là bắt buộc.',
            'integer' => ':attribute phải là số nguyên.',
            'date' => ':attribute không đúng định dạng ngày.',
            'exists' => ':attribute không tồn tại.',
            'in' => ':attribute không hợp lệ.',
            'max' => ':attribute vượt quá giới hạn cho phép.',
            'image' => ':attribute phải là hình ảnh.',
            'mimes' => ':attribute chỉ hỗ trợ jpeg, png, jpg hoặc webp.',
            'regex' => ':attribute phải là số tiền hợp lệ và tối đa 2 chữ số thập phân.',
            'contract_id.required' => 'Vui lòng chọn hợp đồng cần lập hóa đơn.',
            'billing_month.required' => 'Vui lòng chọn tháng lập hóa đơn.',
            'billing_year.required' => 'Vui lòng chọn năm lập hóa đơn.',
            'amount.required' => 'Vui lòng nhập số tiền thanh toán.',
            'payment_method.required' => 'Vui lòng chọn phương thức thanh toán.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
