<?php

namespace App\Http\Requests\Admin\Invoice;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class BulkGenerateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = $this->user('admin');
        if (! $admin) {
            return false;
        }

        if (\App\Helpers\AdminScope::isSuperAdmin($admin)) {
            return true;
        }

        $buildingId = $this->input('building_id');
        if (! $buildingId) {
            return true;
        }

        return \App\Models\Building::query()
            ->whereKey($buildingId)
            ->where('manager_admin_id', $admin->id)
            ->exists();
    }

    protected function failedAuthorization(): void
    {
        $admin = $this->user('admin');
        $message = $admin ? 'Bạn không có quyền quản lý tòa nhà này' : 'Bạn không có quyền';

        throw new HttpResponseException(
            ApiResponse::responseJson(false, $message, 403, null, 403)
        );
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
            'building_id.integer' => 'Tòa nhà phải là số nguyên.',
            'building_id.exists' => 'Tòa nhà không tồn tại.',
            'billing_month.required' => 'Vui lòng chọn tháng.',
            'billing_month.integer' => 'Tháng phải là số nguyên.',
            'billing_month.min' => 'Tháng phải từ 1 đến 12.',
            'billing_month.max' => 'Tháng phải từ 1 đến 12.',
            'billing_year.required' => 'Vui lòng chọn năm.',
            'billing_year.integer' => 'Năm phải là số nguyên.',
            'billing_year.min' => 'Năm phải từ 2020 đến 2100.',
            'billing_year.max' => 'Năm phải từ 2020 đến 2100.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
