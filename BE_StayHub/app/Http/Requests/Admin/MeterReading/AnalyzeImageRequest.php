<?php

namespace App\Http\Requests\Admin\MeterReading;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class AnalyzeImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'image'           => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'meter_type'      => ['required', 'integer', Rule::in([1, 2])],
            'previous_reading' => ['nullable', 'numeric', 'min:0'],
            'old_image_path'  => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'image.required'          => 'Vui lòng chọn ảnh đồng hồ.',
            'image.file'              => 'Tệp tải lên không hợp lệ.',
            'image.image'             => 'Tệp tải lên phải là hình ảnh.',
            'image.mimes'             => 'Ảnh chỉ hỗ trợ định dạng jpg, jpeg, png hoặc webp.',
            'image.max'               => 'Kích thước ảnh tối đa 10MB.',
            'meter_type.required'     => 'Vui lòng chọn loại đồng hồ.',
            'meter_type.integer'      => 'Loại đồng hồ không hợp lệ.',
            'meter_type.in'           => 'Loại đồng hồ không hợp lệ.',
            'previous_reading.numeric' => 'Chỉ số cũ phải là số.',
            'previous_reading.min'    => 'Chỉ số cũ không được âm.',
            'old_image_path.string'   => 'Đường dẫn ảnh cũ không hợp lệ.',
            'old_image_path.max'      => 'Đường dẫn ảnh cũ quá dài.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
