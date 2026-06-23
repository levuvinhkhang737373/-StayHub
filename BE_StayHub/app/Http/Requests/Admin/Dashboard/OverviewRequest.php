<?php

namespace App\Http\Requests\Admin\Dashboard;

use App\Helpers\AdminScope;
use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class OverviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = $this->user('admin');

        return $admin && (AdminScope::isSuperAdmin($admin) || AdminScope::isBuildingManager($admin));
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, 'Bạn không có quyền xem dashboard', 403, null, 403)
        );
    }

    public function rules(): array
    {
        return [
            'building_id' => ['nullable', 'integer', 'exists:buildings,id'],
            'months' => ['nullable', 'integer', 'min:2', 'max:24'],
            'year' => ['nullable', 'integer', 'min:2020', 'max:2100'],
            'month_from' => ['nullable', 'integer', 'min:1', 'max:12'],
            'month_to' => ['nullable', 'integer', 'min:1', 'max:12', 'gte:month_from'],
        ];
    }

    public function messages(): array
    {
        return [
            'building_id.integer' => 'Tòa nhà không hợp lệ.',
            'building_id.exists' => 'Tòa nhà không tồn tại.',
            'months.integer' => 'Số tháng phải là số nguyên.',
            'months.min' => 'Dashboard cần tối thiểu 2 tháng dữ liệu.',
            'months.max' => 'Dashboard chỉ hỗ trợ tối đa 24 tháng dữ liệu.',
            'year.integer' => 'Năm phải là số nguyên.',
            'year.min' => 'Năm không hợp lệ.',
            'year.max' => 'Năm không hợp lệ.',
            'month_from.integer' => 'Tháng bắt đầu phải là số nguyên.',
            'month_from.min' => 'Tháng bắt đầu không hợp lệ.',
            'month_from.max' => 'Tháng bắt đầu không hợp lệ.',
            'month_to.integer' => 'Tháng kết thúc phải là số nguyên.',
            'month_to.min' => 'Tháng kết thúc không hợp lệ.',
            'month_to.max' => 'Tháng kết thúc không hợp lệ.',
            'month_to.gte' => 'Tháng kết thúc phải lớn hơn hoặc bằng tháng bắt đầu.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
