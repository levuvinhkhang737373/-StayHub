<?php

namespace App\Http\Requests\Admin\FireSafetyAlert;

use App\Helpers\ApiResponse;
use App\Models\FireSafetyAlert;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'building_id' => ['nullable', 'integer', 'exists:buildings,id'],
            'security_camera_id' => ['nullable', 'integer', 'exists:security_cameras,id'],
            'risk_level' => ['nullable', 'integer', Rule::in(array_keys(FireSafetyAlert::RISK_LABELS))],
            'status' => ['nullable', 'integer', Rule::in(array_keys(FireSafetyAlert::STATUS_LABELS))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
