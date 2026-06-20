<?php

namespace App\Http\Requests\Admin\Room;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class TranferSingleTenantRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'to_room_id' => ['required', 'integer', 'exists:rooms,id'],
            'movement_date' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:500'],

            // Chỉ số chốt sổ phòng cũ tại ngày chuyển (theo từng meter_device_id thực tế của phòng).
            'meter_readings' => ['nullable', 'array'],
            'meter_readings.*.meter_device_id' => ['required', 'integer', 'exists:meter_devices,id'],
            'meter_readings.*.current_reading' => ['required', 'numeric', 'min:0'],

            // Chỉ số khởi điểm cho công tơ phòng MỚI, chỉ cần khi phòng đó chưa có công tơ.
            // key = service_id, value = chỉ số ban đầu.
            'new_room_opening_readings' => ['nullable', 'array'],
            'new_room_opening_readings.*' => ['numeric', 'min:0'],

            'deposit_settlement_amount' => ['nullable', 'numeric', 'min:0'],
            'deposit_deduction_amount' => ['nullable', 'numeric', 'min:0'],
            'deposit_refund_amount' => ['nullable', 'numeric', 'min:0'],
            'transfer_fee' => ['nullable', 'numeric', 'min:0'],

            'carry_vehicle_ids' => ['nullable', 'array'],
            'carry_vehicle_ids.*' => ['integer', 'exists:vehicles,id'],
        ];
    }
    public function messages(): array
    {
        return [
            'tenant_id.required' => 'Vui lòng chọn khách thuê cần chuyển.',
            'to_room_id.required' => 'Vui lòng chọn phòng đích.',
            'to_room_id.exists' => 'Phòng đích không tồn tại.',
            'movement_date.before_or_equal' => 'Ngày chuyển không được ở tương lai.',
        ];
    }
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
