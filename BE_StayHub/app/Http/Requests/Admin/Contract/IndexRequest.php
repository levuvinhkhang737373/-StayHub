<?php

namespace App\Http\Requests\Admin\Contract;

use App\Helpers\ApiResponse;
use App\Models\Contract;
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
            'keyword' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'integer', Rule::in(array_keys(Contract::STATUS_LABELS))],
            'building_id' => ['nullable', 'integer', Rule::exists('buildings', 'id')],
            'room_id' => ['nullable', 'integer', Rule::exists('rooms', 'id')],

            'created_by' => ['nullable', 'integer', Rule::exists('admins', 'id')],
            'start_date_from' => ['nullable', 'date_format:Y-m-d'],
            'start_date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date_from'],
            'end_date_from' => ['nullable', 'date_format:Y-m-d'],
            'end_date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:end_date_from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'keyword.string' => 'Từ khóa tìm kiếm hợp đồng phải là chuỗi ký tự.',
            'keyword.max' => 'Từ khóa tìm kiếm hợp đồng không được vượt quá 120 ký tự.',
            'status.integer' => 'Trạng thái hợp đồng không hợp lệ.',
            'status.in' => 'Trạng thái hợp đồng không nằm trong danh sách cho phép.',
            'building_id.integer' => 'Tòa nhà không hợp lệ.',
            'building_id.exists' => 'Tòa nhà không tồn tại.',
            'room_id.integer' => 'Phòng không hợp lệ.',
            'room_id.exists' => 'Phòng không tồn tại.',

            'created_by.integer' => 'Người tạo hợp đồng không hợp lệ.',
            'created_by.exists' => 'Người tạo hợp đồng không tồn tại.',
            'start_date_from.date_format' => 'Ngày bắt đầu từ phải đúng định dạng YYYY-MM-DD.',
            'start_date_to.date_format' => 'Ngày bắt đầu đến phải đúng định dạng YYYY-MM-DD.',
            'start_date_to.after_or_equal' => 'Ngày bắt đầu đến phải lớn hơn hoặc bằng ngày bắt đầu từ.',
            'end_date_from.date_format' => 'Ngày kết thúc từ phải đúng định dạng YYYY-MM-DD.',
            'end_date_to.date_format' => 'Ngày kết thúc đến phải đúng định dạng YYYY-MM-DD.',
            'end_date_to.after_or_equal' => 'Ngày kết thúc đến phải lớn hơn hoặc bằng ngày kết thúc từ.',
            'page.integer' => 'Trang hiện tại không hợp lệ.',
            'page.min' => 'Trang hiện tại tối thiểu là 1.',
            'per_page.integer' => 'Số lượng hợp đồng mỗi trang không hợp lệ.',
            'per_page.min' => 'Số lượng hợp đồng mỗi trang tối thiểu là 1.',
            'per_page.max' => 'Số lượng hợp đồng mỗi trang tối đa là 100.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
