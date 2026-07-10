<?php

namespace App\Http\Requests\Admin\RoomServicePrice;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('admin');
    }

    public function rules(): array
    {
        return [
            'billing_month' => ['required', 'integer', 'min:1', 'max:12'],
            'billing_year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'prices' => ['required', 'array', 'min:1', 'max:100'],
            'prices.*.room_service_id' => ['required', 'integer', 'distinct', 'exists:room_services,id'],
            'prices.*.price' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'billing_month.required' => 'Tháng áp dụng là bắt buộc.',
            'billing_year.required' => 'Năm áp dụng là bắt buộc.',
            'prices.required' => 'Danh sách giá dịch vụ là bắt buộc.',
            'prices.array' => 'Danh sách giá dịch vụ không hợp lệ.',
            'prices.min' => 'Vui lòng gửi ít nhất một giá dịch vụ.',
            'prices.max' => 'Mỗi lần chỉ được cập nhật tối đa 100 dịch vụ.',
            'prices.*.room_service_id.required' => 'Dịch vụ phòng là bắt buộc.',
            'prices.*.room_service_id.integer' => 'Dịch vụ phòng không hợp lệ.',
            'prices.*.room_service_id.distinct' => 'Danh sách dịch vụ phòng không được trùng lặp.',
            'prices.*.room_service_id.exists' => 'Dịch vụ phòng không tồn tại.',
            'prices.*.price.required' => 'Giá dịch vụ là bắt buộc.',
            'prices.*.price.regex' => 'Giá dịch vụ phải là số tiền hợp lệ và tối đa 2 số thập phân.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
