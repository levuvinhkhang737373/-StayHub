<?php

namespace App\Http\Requests\Admin\Contract;

use App\Helpers\ApiResponse;
use App\Models\Contract;
use App\Models\ContractDepositTransaction;
use App\Models\ContractVehicle;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contract_code' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_.-]+$/'],
            'room_id' => ['required', 'integer', Rule::exists('rooms', 'id')],

            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'actual_end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'billing_cycle_day' => ['required', 'integer', 'min:1', 'max:28'],
            'room_price' => ['required', 'regex:/^\d{1,13}(\.\d{1,2})?$/'],
            'deposit_amount' => ['required', 'regex:/^\d{1,13}(\.\d{1,2})?$/'],
            'status' => ['nullable', 'integer', Rule::in(array_keys(Contract::STATUS_LABELS))],
            'contract_files' => ['nullable', 'array', 'max:10'],
            'contract_files.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:20480'],
            'note' => ['nullable', 'string', 'max:2000'],
            'parent_contract_id' => ['nullable', 'integer', Rule::exists('contracts', 'id')],
            'renew_from_contract_id' => ['nullable', 'integer', Rule::exists('contracts', 'id')],
            'is_deposit_paid' => ['nullable', 'boolean'],
            'deposit_payment_method' => ['nullable', 'integer', Rule::in([
                ContractDepositTransaction::PAYMENT_METHOD_CASH,
                ContractDepositTransaction::PAYMENT_METHOD_BANK_TRANSFER
            ])],

            'tenants' => ['required', 'array', 'min:1', 'max:50'],
            'tenants.*.tenant_id' => ['required', 'integer', 'distinct', Rule::exists('tenants', 'id')],
            'tenants.*.join_date' => ['required', 'date_format:Y-m-d'],
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
        return array_merge($this->baseMessages(), $this->tenantMessages(), $this->vehicleMessages());
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }

    private function baseMessages(): array
    {
        return [
            'contract_code.string' => 'Mã hợp đồng phải là chuỗi ký tự.',
            'contract_code.max' => 'Mã hợp đồng không được vượt quá 100 ký tự.',
            'contract_code.regex' => 'Mã hợp đồng chỉ được chứa chữ, số, dấu gạch ngang, gạch dưới hoặc dấu chấm.',
            'room_id.required' => 'Phòng ký hợp đồng là bắt buộc.',
            'room_id.integer' => 'Phòng ký hợp đồng không hợp lệ.',
            'room_id.exists' => 'Phòng ký hợp đồng không tồn tại.',

            'start_date.required' => 'Ngày bắt đầu hợp đồng là bắt buộc.',
            'start_date.date_format' => 'Ngày bắt đầu hợp đồng phải đúng định dạng YYYY-MM-DD.',
            'end_date.required' => 'Ngày kết thúc hợp đồng là bắt buộc.',
            'end_date.date_format' => 'Ngày kết thúc hợp đồng phải đúng định dạng YYYY-MM-DD.',
            'end_date.after_or_equal' => 'Ngày kết thúc hợp đồng phải lớn hơn hoặc bằng ngày bắt đầu.',
            'actual_end_date.date_format' => 'Ngày kết thúc thực tế phải đúng định dạng YYYY-MM-DD.',
            'actual_end_date.after_or_equal' => 'Ngày kết thúc thực tế phải lớn hơn hoặc bằng ngày bắt đầu.',
            'billing_cycle_day.required' => 'Ngày chốt tiền hằng tháng là bắt buộc.',
            'billing_cycle_day.integer' => 'Ngày chốt tiền hằng tháng phải là số nguyên.',
            'billing_cycle_day.min' => 'Ngày chốt tiền hằng tháng tối thiểu là ngày 1.',
            'billing_cycle_day.max' => 'Ngày chốt tiền hằng tháng tối đa là ngày 28 để tránh lỗi tháng thiếu ngày.',
            'room_price.required' => 'Giá phòng trong hợp đồng là bắt buộc.',
            'room_price.regex' => 'Giá phòng phải là số tiền hợp lệ, không âm và tối đa 2 chữ số thập phân.',
            'deposit_amount.required' => 'Tiền cọc trong hợp đồng là bắt buộc.',
            'deposit_amount.regex' => 'Tiền cọc phải là số tiền hợp lệ, không âm và tối đa 2 chữ số thập phân.',
            'status.integer' => 'Trạng thái hợp đồng không hợp lệ.',
            'status.in' => 'Trạng thái hợp đồng không nằm trong danh sách cho phép.',
            'contract_files.array' => 'Danh sách file hợp đồng không hợp lệ.',
            'contract_files.max' => 'Mỗi hợp đồng chỉ được tải tối đa 10 file.',
            'contract_files.*.file' => 'File hợp đồng không hợp lệ.',
            'contract_files.*.mimes' => 'File hợp đồng chỉ hỗ trợ pdf, jpg, jpeg, png hoặc webp.',
            'contract_files.*.max' => 'Mỗi file hợp đồng không được vượt quá 20MB.',
            'note.string' => 'Ghi chú hợp đồng phải là chuỗi ký tự.',
            'note.max' => 'Ghi chú hợp đồng không được vượt quá 2000 ký tự.',
            'parent_contract_id.integer' => 'ID hợp đồng cha phải là số nguyên.',
            'parent_contract_id.exists' => 'ID hợp đồng cha không tồn tại.',
            'renew_from_contract_id.integer' => 'ID hợp đồng gốc gia hạn phải là số nguyên.',
            'renew_from_contract_id.exists' => 'ID hợp đồng gốc gia hạn không tồn tại.',
        ];
    }

    private function tenantMessages(): array
    {
        return [
            'tenants.required' => 'Danh sách khách thuê trong hợp đồng là bắt buộc.',
            'tenants.array' => 'Danh sách khách thuê trong hợp đồng không hợp lệ.',
            'tenants.min' => 'Hợp đồng phải có ít nhất 1 khách thuê.',
            'tenants.max' => 'Hợp đồng không được vượt quá 50 khách thuê.',
            'tenants.*.tenant_id.required' => 'Khách thuê trong hợp đồng là bắt buộc.',
            'tenants.*.tenant_id.integer' => 'Khách thuê trong hợp đồng không hợp lệ.',
            'tenants.*.tenant_id.distinct' => 'Không được chọn trùng khách thuê trong cùng hợp đồng.',
            'tenants.*.tenant_id.exists' => 'Khách thuê trong hợp đồng không tồn tại.',
            'tenants.*.join_date.required' => 'Ngày vào ở của khách thuê là bắt buộc.',
            'tenants.*.join_date.date_format' => 'Ngày vào ở của khách thuê phải đúng định dạng YYYY-MM-DD.',
            'tenants.*.leave_date.date_format' => 'Ngày rời đi của khách thuê phải đúng định dạng YYYY-MM-DD.',
            'tenants.*.leave_date.after_or_equal' => 'Ngày rời đi phải lớn hơn hoặc bằng ngày vào ở.',
            'tenants.*.billing_start_date.date_format' => 'Ngày bắt đầu tính tiền của khách thuê phải đúng định dạng YYYY-MM-DD.',
            'tenants.*.billing_end_date.date_format' => 'Ngày kết thúc tính tiền của khách thuê phải đúng định dạng YYYY-MM-DD.',
            'tenants.*.billing_end_date.after_or_equal' => 'Ngày kết thúc tính tiền phải lớn hơn hoặc bằng ngày bắt đầu tính tiền.',

            'tenants.*.is_staying.boolean' => 'Trạng thái đang ở của khách thuê không hợp lệ.',
        ];
    }

    private function vehicleMessages(): array
    {
        return [
            'vehicles.array' => 'Danh sách phương tiện trong hợp đồng không hợp lệ.',
            'vehicles.max' => 'Hợp đồng không được vượt quá 50 phương tiện.',
            'vehicles.*.vehicle_id.required_with' => 'Phương tiện trong hợp đồng là bắt buộc.',
            'vehicles.*.vehicle_id.integer' => 'Phương tiện trong hợp đồng không hợp lệ.',
            'vehicles.*.vehicle_id.distinct' => 'Không được chọn trùng phương tiện trong cùng hợp đồng.',
            'vehicles.*.vehicle_id.exists' => 'Phương tiện trong hợp đồng không tồn tại.',
            'vehicles.*.started_at.required_with' => 'Ngày bắt đầu gửi xe là bắt buộc.',
            'vehicles.*.started_at.date_format' => 'Ngày bắt đầu gửi xe phải đúng định dạng YYYY-MM-DD.',
            'vehicles.*.ended_at.date_format' => 'Ngày kết thúc gửi xe phải đúng định dạng YYYY-MM-DD.',
            'vehicles.*.ended_at.after_or_equal' => 'Ngày kết thúc gửi xe phải lớn hơn hoặc bằng ngày bắt đầu gửi xe.',
            'vehicles.*.billing_start_date.date_format' => 'Ngày bắt đầu tính phí xe phải đúng định dạng YYYY-MM-DD.',
            'vehicles.*.billing_end_date.date_format' => 'Ngày kết thúc tính phí xe phải đúng định dạng YYYY-MM-DD.',
            'vehicles.*.billing_end_date.after_or_equal' => 'Ngày kết thúc tính phí xe phải lớn hơn hoặc bằng ngày bắt đầu tính phí.',
            'vehicles.*.monthly_fee.regex' => 'Phí gửi xe phải là số tiền hợp lệ, không âm và tối đa 2 chữ số thập phân.',
            'vehicles.*.charge_policy.required_with' => 'Chính sách tính phí xe là bắt buộc.',
            'vehicles.*.charge_policy.integer' => 'Chính sách tính phí xe không hợp lệ.',
            'vehicles.*.charge_policy.in' => 'Chính sách tính phí xe không nằm trong danh sách cho phép.',
            'vehicles.*.is_active.boolean' => 'Trạng thái tính phí xe không hợp lệ.',
        ];
    }
}
