<?php

namespace App\Http\Requests\Admin\RoomServicePrice;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('admin');
    }

    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'building_id' => ['nullable', 'integer', 'exists:buildings,id'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'keyword' => ['nullable', 'string', 'max:100'],
            'billing_month' => ['required', 'integer', 'min:1', 'max:12'],
            'billing_year' => ['required', 'integer', 'min:2020', 'max:2100'],
        ];
    }

    public function messages(): array
    {
        return [
            'building_id.exists' => 'Tòa nhà không tồn tại.',
            'room_id.exists' => 'Phòng không tồn tại.',
            'billing_month.required' => 'Tháng áp dụng là bắt buộc.',
            'billing_month.min' => 'Tháng áp dụng không hợp lệ.',
            'billing_month.max' => 'Tháng áp dụng không hợp lệ.',
            'billing_year.required' => 'Năm áp dụng là bắt buộc.',
            'billing_year.min' => 'Năm áp dụng không hợp lệ.',
            'billing_year.max' => 'Năm áp dụng không hợp lệ.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
