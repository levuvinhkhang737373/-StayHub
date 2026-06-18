<?php

namespace App\Http\Requests\Tenant;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class SignContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:150'],
            'identity_number' => ['required', 'string', 'max:30'],
            'identity_type' => ['required', 'integer', 'in:1,2,3'],
            'identity_date' => ['required', 'date_format:Y-m-d'],
            'identity_place' => ['required', 'string', 'max:255'],
            'permanent_address' => ['required', 'string', 'max:500'],
            'signature_file' => ['required', 'file', 'image', 'mimes:png,jpg,jpeg', 'max:2048'], // max 2MB
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'Họ và tên là bắt buộc.',
            'full_name.string' => 'Họ và tên phải là chuỗi ký tự.',
            'full_name.max' => 'Họ và tên tối đa 150 ký tự.',
            'identity_number.required' => 'Số CMND/CCCD là bắt buộc.',
            'identity_number.string' => 'Số CMND/CCCD phải là chuỗi.',
            'identity_number.max' => 'Số CMND/CCCD tối đa 30 ký tự.',
            'identity_type.required' => 'Loại giấy tờ là bắt buộc.',
            'identity_type.integer' => 'Loại giấy tờ không hợp lệ.',
            'identity_type.in' => 'Loại giấy tờ không hợp lệ (1: CCCD, 2: CMND, 3: Hộ chiếu).',
            'identity_date.required' => 'Ngày cấp là bắt buộc.',
            'identity_date.date_format' => 'Ngày cấp phải đúng định dạng YYYY-MM-DD.',
            'identity_place.required' => 'Nơi cấp là bắt buộc.',
            'identity_place.string' => 'Nơi cấp phải là chuỗi.',
            'identity_place.max' => 'Nơi cấp tối đa 255 ký tự.',
            'permanent_address.required' => 'Địa chỉ thường trú là bắt buộc.',
            'permanent_address.string' => 'Địa chỉ thường trú phải là chuỗi.',
            'permanent_address.max' => 'Địa chỉ thường trú tối đa 500 ký tự.',
            'signature_file.required' => 'Chữ ký vẽ tay là bắt buộc.',
            'signature_file.file' => 'Chữ ký phải là một file ảnh.',
            'signature_file.image' => 'Chữ ký phải là một định dạng hình ảnh.',
            'signature_file.mimes' => 'Chữ ký phải thuộc định dạng: png, jpg, jpeg.',
            'signature_file.max' => 'Dung lượng ảnh chữ ký tối đa là 2MB.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
