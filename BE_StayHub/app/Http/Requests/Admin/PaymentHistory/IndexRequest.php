<?php

namespace App\Http\Requests\Admin\PaymentHistory;

use App\Helpers\AdminScope;
use App\Helpers\ApiResponse;
use App\Models\ContractDepositTransaction;
use App\Models\Payment;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class IndexRequest extends FormRequest
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
            ApiResponse::responseJson(false, 'Bạn không có quyền xem lịch sử thanh toán', 403, null, 403)
        );
    }

    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:150'],
            'source_type' => ['nullable', 'string', Rule::in(['invoice_payment', 'deposit_transaction', 'room_transfer'])],
            'status_group' => ['nullable', 'string', Rule::in(['pending', 'confirmed', 'cancelled', 'partial', 'paid'])],
            'payment_method' => ['nullable', 'integer', Rule::in([
                Payment::PAYMENT_METHOD_CASH,
                Payment::PAYMENT_METHOD_BANK_TRANSFER,
            ])],
            'building_id' => ['nullable', 'integer', Rule::exists('buildings', 'id')],
            'room_id' => ['nullable', 'integer', Rule::exists('rooms', 'id')],
            'contract_id' => ['nullable', 'integer', Rule::exists('contracts', 'id')],
            'invoice_id' => ['nullable', 'integer', Rule::exists('invoices', 'id')],
            'deposit_transaction_type' => ['nullable', 'integer', Rule::in([
                ContractDepositTransaction::TRANSACTION_TYPE_COLLECT,
                ContractDepositTransaction::TRANSACTION_TYPE_REFUND,
            ])],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'amount_direction' => ['nullable', 'string', Rule::in(['in', 'out', 'adjustment'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'keyword.string' => 'Từ khóa lịch sử thanh toán phải là chuỗi ký tự.',
            'keyword.max' => 'Từ khóa lịch sử thanh toán không được vượt quá 150 ký tự.',
            'source_type.in' => 'Loại nguồn thanh toán không hợp lệ.',
            'status_group.in' => 'Nhóm trạng thái thanh toán không hợp lệ.',
            'payment_method.in' => 'Phương thức thanh toán không hợp lệ.',
            'building_id.exists' => 'Tòa nhà lọc lịch sử thanh toán không tồn tại.',
            'room_id.exists' => 'Phòng lọc lịch sử thanh toán không tồn tại.',
            'contract_id.exists' => 'Hợp đồng lọc lịch sử thanh toán không tồn tại.',
            'invoice_id.exists' => 'Hóa đơn lọc lịch sử thanh toán không tồn tại.',
            'deposit_transaction_type.in' => 'Loại giao dịch cọc không hợp lệ.',
            'date_from.date_format' => 'Ngày bắt đầu phải đúng định dạng YYYY-MM-DD.',
            'date_to.date_format' => 'Ngày kết thúc phải đúng định dạng YYYY-MM-DD.',
            'date_to.after_or_equal' => 'Ngày kết thúc phải lớn hơn hoặc bằng ngày bắt đầu.',
            'amount_direction.in' => 'Chiều tiền không hợp lệ.',
            'page.integer' => 'Trang hiện tại không hợp lệ.',
            'page.min' => 'Trang hiện tại tối thiểu là 1.',
            'per_page.integer' => 'Số dòng mỗi trang không hợp lệ.',
            'per_page.min' => 'Số dòng mỗi trang tối thiểu là 1.',
            'per_page.max' => 'Số dòng mỗi trang tối đa là 100.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
