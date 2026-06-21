<?php

namespace App\Http\Requests\Admin\MeterReading;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class InitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'building_id' => ['required', 'integer', 'exists:buildings,id'],
            'billing_month' => ['required', 'integer', 'min:1', 'max:12'],
            'billing_year' => ['required', 'integer', 'min:2020', 'max:2100'],
        ];
    }

    public function messages(): array
    {
        return [
            'building_id.required' => 'Vui lòng chọn tòa nhà.',
            'building_id.integer' => 'Tòa nhà không hợp lệ.',
            'building_id.exists' => 'Tòa nhà không tồn tại.',
            'billing_month.required' => 'Vui lòng chọn tháng chốt.',
            'billing_month.integer' => 'Tháng chốt không hợp lệ.',
            'billing_month.min' => 'Tháng chốt không hợp lệ.',
            'billing_month.max' => 'Tháng chốt không hợp lệ.',
            'billing_year.required' => 'Vui lòng chọn năm chốt.',
            'billing_year.integer' => 'Năm chốt không hợp lệ.',
            'billing_year.min' => 'Năm chốt không hợp lệ.',
            'billing_year.max' => 'Năm chốt không hợp lệ.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
