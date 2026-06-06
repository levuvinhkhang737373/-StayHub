<?php

namespace App\Http\Requests\Admin\Meter;

use App\Helpers\ApiResponse;
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
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'meter_type' => ['nullable', 'integer', Rule::in([1, 2])],
            'status' => ['nullable', 'integer', Rule::in([1, 2, 3, 4])],
            'keyword' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'page.integer' => 'Trang hiện tại phải là số nguyên.',
            'page.min' => 'Trang hiện tại tối thiểu là 1.',
            'per_page.integer' => 'Số bản ghi mỗi trang phải là số nguyên.',
            'per_page.min' => 'Số bản ghi mỗi trang tối thiểu là 1.',
            'per_page.max' => 'Số bản ghi mỗi trang tối đa là 1000.',
            'room_id.integer' => 'ID phòng không hợp lệ.',
            'room_id.exists' => 'Phòng không tồn tại.',
            'service_id.integer' => 'ID dịch vụ không hợp lệ.',
            'service_id.exists' => 'Dịch vụ không tồn tại.',
            'meter_type.integer' => 'Loại đồng hồ không hợp lệ.',
            'meter_type.in' => 'Loại đồng hồ không nằm trong danh sách cho phép.',
            'status.integer' => 'Trạng thái không hợp lệ.',
            'status.in' => 'Trạng thái không nằm trong danh sách cho phép.',
            'keyword.string' => 'Từ khóa tìm kiếm phải là chuỗi ký tự.',
            'keyword.max' => 'Từ khóa tìm kiếm không được vượt quá 100 ký tự.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
