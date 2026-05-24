<?php

namespace App\Http\Requests\Admin\Building;

use App\Helpers\ApiResponse;
use App\Models\Building;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'integer', Rule::in(array_keys(Building::STATUS_LABELS))],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Trạng thái tòa nhà là bắt buộc.',
            'status.integer' => 'Trạng thái tòa nhà không hợp lệ.',
            'status.in' => 'Trạng thái tòa nhà không nằm trong danh sách cho phép.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
