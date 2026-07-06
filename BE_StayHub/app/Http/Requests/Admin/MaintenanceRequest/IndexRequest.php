<?php

namespace App\Http\Requests\Admin\MaintenanceRequest;

use App\Helpers\ApiResponse;
use App\Models\MaintenanceRequest;
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
            'status' => ['nullable', 'integer', Rule::in(array_keys(MaintenanceRequest::STATUS_LABELS))],
            'building_id' => ['nullable', 'integer', 'exists:buildings,id'],
            'room_number' => ['nullable', 'string', 'max:50'],
            'keyword' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.integer' => 'Trạng thái phiếu bảo trì không hợp lệ.',
            'status.in' => 'Trạng thái phiếu bảo trì không nằm trong danh sách cho phép.',
            'building_id.integer' => 'Tòa nhà của phiếu bảo trì không hợp lệ.',
            'building_id.exists' => 'Tòa nhà của phiếu bảo trì không tồn tại.',
            'room_number.string' => 'Số phòng phải là chuỗi ký tự.',
            'room_number.max' => 'Số phòng không được vượt quá 50 ký tự.',
            'keyword.string' => 'Từ khóa tìm kiếm phải là chuỗi ký tự.',
            'keyword.max' => 'Từ khóa tìm kiếm không được vượt quá 100 ký tự.',
            'per_page.integer' => 'Số dòng mỗi trang không hợp lệ.',
            'per_page.min' => 'Số dòng mỗi trang tối thiểu là 1.',
            'per_page.max' => 'Số dòng mỗi trang tối đa là 100.',
            'page.integer' => 'Trang hiện tại không hợp lệ.',
            'page.min' => 'Trang hiện tại tối thiểu là 1.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
