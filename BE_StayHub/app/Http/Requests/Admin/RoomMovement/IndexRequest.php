<?php

namespace App\Http\Requests\Admin\RoomMovement;

use App\Helpers\ApiResponse;
use App\Models\RoomMovement;
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
            'keyword' => ['nullable', 'string', 'max:255'],
            'movement_type' => ['nullable', 'integer', Rule::in(array_keys(RoomMovement::MOVEMENT_TYPE_LABELS))],
            'status' => ['nullable', 'integer', Rule::in(array_keys(RoomMovement::STATUS_LABELS))],
            'building_id' => ['nullable', 'integer', Rule::exists('buildings', 'id')],
            'room_id' => ['nullable', 'integer', Rule::exists('rooms', 'id')],
            'tenant_id' => ['nullable', 'integer', Rule::exists('tenants', 'id')],
            'contract_id' => ['nullable', 'integer', Rule::exists('contracts', 'id')],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'keyword.string' => 'Từ khóa lịch sử phòng phải là chuỗi ký tự.',
            'keyword.max' => 'Từ khóa lịch sử phòng không được vượt quá 255 ký tự.',
            'movement_type.in' => 'Loại biến động phòng không hợp lệ.',
            'status.in' => 'Trạng thái lịch chuyển phòng không hợp lệ.',
            'building_id.exists' => 'Tòa nhà lọc lịch sử không tồn tại.',
            'room_id.exists' => 'Phòng lọc lịch sử không tồn tại.',
            'tenant_id.exists' => 'Khách thuê lọc lịch sử không tồn tại.',
            'contract_id.exists' => 'Hợp đồng lọc lịch sử không tồn tại.',
            'date_from.date_format' => 'Ngày bắt đầu phải đúng định dạng YYYY-MM-DD.',
            'date_to.date_format' => 'Ngày kết thúc phải đúng định dạng YYYY-MM-DD.',
            'date_to.after_or_equal' => 'Ngày kết thúc phải lớn hơn hoặc bằng ngày bắt đầu.',
            'page.integer' => 'Trang hiện tại không hợp lệ.',
            'page.min' => 'Trang hiện tại tối thiểu là 1.',
            'per_page.integer' => 'Số bản ghi mỗi trang không hợp lệ.',
            'per_page.min' => 'Số bản ghi mỗi trang tối thiểu là 1.',
            'per_page.max' => 'Số bản ghi mỗi trang tối đa là 100.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
