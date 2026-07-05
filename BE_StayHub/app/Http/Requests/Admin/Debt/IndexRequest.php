<?php

namespace App\Http\Requests\Admin\Debt;

use App\Helpers\AdminScope;
use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class IndexRequest extends FormRequest
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
            ApiResponse::responseJson(false, 'Bạn không có quyền xem công nợ', 403, null, 403)
        );
    }

    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'keyword' => ['nullable', 'string', 'max:100'],
            'debt_status' => ['nullable', 'string', 'in:all,collectible,rolled,overdue'],
            'building_id' => ['nullable', 'integer', 'exists:buildings,id'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'contract_id' => ['nullable', 'integer', 'exists:contracts,id'],
            'billing_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'billing_year' => ['nullable', 'integer', 'min:2020', 'max:2100'],
        ];
    }

    public function messages(): array
    {
        return [
            'integer' => ':attribute phải là số nguyên.',
            'exists' => ':attribute không tồn tại.',
            'in' => ':attribute không hợp lệ.',
            'min' => ':attribute không hợp lệ.',
            'max' => ':attribute vượt quá giới hạn cho phép.',
            'keyword.max' => 'Từ khóa tìm kiếm tối đa 100 ký tự.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
