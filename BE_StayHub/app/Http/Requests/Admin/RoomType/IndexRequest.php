<?php

namespace App\Http\Requests\Admin\RoomType;

use App\Helpers\ApiResponse;
use App\Models\RoomType;
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
            'building_id' => ['nullable', 'integer', 'exists:buildings,id'],
            'status' => ['nullable', 'integer', Rule::in(array_keys(RoomType::STATUS_LABELS))],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'gte:min_price'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'keyword.string' => 'Từ khóa tìm kiếm loại phòng phải là chuỗi ký tự.',
            'keyword.max' => 'Từ khóa tìm kiếm loại phòng không được vượt quá 255 ký tự.',
            'building_id.integer' => 'Tòa nhà của loại phòng không hợp lệ.',
            'building_id.exists' => 'Tòa nhà của loại phòng không tồn tại.',
            'status.integer' => 'Trạng thái loại phòng không hợp lệ.',
            'status.in' => 'Trạng thái loại phòng không nằm trong danh sách cho phép.',
            'min_price.numeric' => 'Giá nhỏ nhất phải là số.',
            'min_price.min' => 'Giá nhỏ nhất không được nhỏ hơn 0.',
            'max_price.numeric' => 'Giá lớn nhất phải là số.',
            'max_price.min' => 'Giá lớn nhất không được nhỏ hơn 0.',
            'max_price.gte' => 'Giá lớn nhất phải lớn hơn hoặc bằng giá nhỏ nhất.',
            'per_page.integer' => 'Số dòng mỗi trang phải là số nguyên.',
            'per_page.min' => 'Số dòng mỗi trang phải lớn hơn hoặc bằng 1.',
            'per_page.max' => 'Số dòng mỗi trang không được vượt quá 100.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
