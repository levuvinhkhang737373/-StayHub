<?php

namespace App\Http\Requests\Admin\Contract;

use App\Models\ContractDepositTransaction;
use App\Models\ContractVehicle;
use Illuminate\Validation\Rule;

class UpdateRequest extends RegisterRequest
{
    public function rules(): array
    {
        return [
            'contract_code' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_.-]+$/'],
            'room_id' => ['nullable', 'integer', Rule::exists('rooms', 'id')],

            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'actual_end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'billing_cycle_day' => ['nullable', 'integer', 'min:1', 'max:28'],
            'room_price' => ['nullable', 'regex:/^\d{1,13}(\.\d{1,2})?$/'],
            'deposit_amount' => ['nullable', 'regex:/^\d{1,13}(\.\d{1,2})?$/', 'gt:0'],
            'status' => ['prohibited'],
            'contract_files' => ['nullable', 'array', 'max:10'],
            'contract_files.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:20480'],
            'delete_contract_files' => ['nullable', 'array', 'max:10'],
            'delete_contract_files.*' => ['string', 'max:500'],
            'note' => ['nullable', 'string', 'max:2000'],
            'parent_contract_id' => ['nullable', 'integer', Rule::exists('contracts', 'id')],
            'renew_from_contract_id' => ['nullable', 'integer', Rule::exists('contracts', 'id')],
            'is_deposit_paid' => ['nullable', 'boolean'],
            'deposit_payment_method' => ['nullable', 'integer', Rule::in([
                ContractDepositTransaction::PAYMENT_METHOD_CASH,
                ContractDepositTransaction::PAYMENT_METHOD_BANK_TRANSFER
            ])],

            'tenants' => ['nullable', 'array', 'min:1', 'max:50'],
            'tenants.*.tenant_id' => ['required_with:tenants', 'integer', 'distinct', Rule::exists('tenants', 'id')],
            'tenants.*.join_date' => ['required_with:tenants', 'date_format:Y-m-d'],
            'tenants.*.leave_date' => ['nullable', 'date_format:Y-m-d'],
            'tenants.*.billing_start_date' => ['nullable', 'date_format:Y-m-d'],
            'tenants.*.billing_end_date' => ['nullable', 'date_format:Y-m-d'],

            'tenants.*.is_staying' => ['nullable', 'boolean'],

            'vehicles' => ['nullable', 'array', 'max:50'],
            'vehicles.*.vehicle_id' => ['required_with:vehicles', 'integer', 'distinct', Rule::exists('vehicles', 'id')],
            'vehicles.*.started_at' => ['required_with:vehicles', 'date_format:Y-m-d'],
            'vehicles.*.ended_at' => ['nullable', 'date_format:Y-m-d'],
            'vehicles.*.billing_start_date' => ['nullable', 'date_format:Y-m-d'],
            'vehicles.*.billing_end_date' => ['nullable', 'date_format:Y-m-d'],
            'vehicles.*.monthly_fee' => ['nullable', 'regex:/^\d{1,13}(\.\d{1,2})?$/'],
            'vehicles.*.charge_policy' => ['required_with:vehicles', 'integer', Rule::in(array_keys(ContractVehicle::CHARGE_POLICY_LABELS))],
            'vehicles.*.is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'status.prohibited' => 'Vui lòng đổi trạng thái hợp đồng bằng chức năng cập nhật trạng thái riêng.',
            'delete_contract_files.array' => 'Danh sách file hợp đồng cần xóa không hợp lệ.',
            'delete_contract_files.max' => 'Mỗi lần chỉ được xóa tối đa 10 file hợp đồng.',
            'delete_contract_files.*.string' => 'Đường dẫn file hợp đồng cần xóa không hợp lệ.',
            'delete_contract_files.*.max' => 'Đường dẫn file hợp đồng cần xóa không được vượt quá 500 ký tự.',
        ]);
    }
}
