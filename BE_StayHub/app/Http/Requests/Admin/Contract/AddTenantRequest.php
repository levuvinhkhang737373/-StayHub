<?php

namespace App\Http\Requests\Admin\Contract;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class AddTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'integer', Rule::exists('tenants', 'id')],
            'join_date' => ['required', 'date_format:Y-m-d'],
            'billing_start_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:join_date'],
        ];
    }

    public function messages(): array
    {
        return [
            'tenant_id.required' => 'Vui lòng chọn khách thuê cần thêm vào hợp đồng.',
            'tenant_id.integer' => 'Khách thuê cần thêm không hợp lệ.',
            'tenant_id.exists' => 'Khách thuê cần thêm không tồn tại.',
            'join_date.required' => 'Ngày vào ở là bắt buộc.',
            'join_date.date_format' => 'Ngày vào ở phải đúng định dạng YYYY-MM-DD.',
            'billing_start_date.date_format' => 'Ngày bắt đầu tính tiền phải đúng định dạng YYYY-MM-DD.',
            'billing_start_date.after_or_equal' => 'Ngày bắt đầu tính tiền phải lớn hơn hoặc bằng ngày vào ở.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
