<?php

namespace App\Http\Requests\Admin\Invoice;

use App\Helpers\AdminScope;
use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateRequest extends FormRequest
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
            ApiResponse::responseJson(false, 'Bạn không có quyền cập nhật hóa đơn', 403, null, 403)
        );
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
            'due_date' => ['nullable', 'date'],
            'meter_readings' => ['nullable', 'array', 'max:20'],
            'meter_readings.*.meter_reading_id' => ['required_with:meter_readings', 'integer', 'distinct', 'exists:meter_readings,id'],
            'meter_readings.*.current_reading' => ['required_with:meter_readings', 'numeric', 'min:0'],
            'meter_readings.*.reading_date' => ['nullable', 'date'],
            'meter_readings.*.note' => ['nullable', 'string', 'max:500'],
            'adjustments' => ['nullable', 'array', 'max:50'],
            'adjustments.*.item_type' => ['required_with:adjustments', 'integer', 'in:7,8,10,11'],
            'adjustments.*.description' => ['required_with:adjustments', 'string', 'max:255'],
            'adjustments.*.quantity' => ['nullable', 'regex:/^\d+(\.\d{1,2})?$/'],
            'adjustments.*.unit_price' => ['required_with:adjustments', 'regex:/^\d+(\.\d{1,2})?$/'],
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
            'reason.required' => 'Vui lòng nhập lý do phát hành lại hóa đơn.',
            'meter_readings.*.meter_reading_id.required_with' => 'Vui lòng chọn chỉ số cần chỉnh sửa.',
            'meter_readings.*.current_reading.required_with' => 'Vui lòng nhập chỉ số mới.',
            'meter_readings.*.current_reading.numeric' => 'Chỉ số mới phải là số.',
            'meter_readings.*.current_reading.min' => 'Chỉ số mới không được âm.',
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
