<?php

namespace App\Http\Requests\Admin\Auth;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $adminId = $this->user('admin')?->id;

        return [
            'full_name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'regex:/^(0[3|5|7|8|9])+([0-9]{8})$/'],
            'email' => ['sometimes', 'required', 'email', 'max:150', Rule::unique('admins', 'email')->ignore($adminId)],
            'avatar' => ['nullable', 'image', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'Họ tên không được để trống.',
            'full_name.string' => 'Họ tên phải là chuỗi ký tự.',
            'full_name.max' => 'Họ tên không được vượt quá 150 ký tự.',
            'phone.regex' => 'Số điện thoại không đúng định dạng Việt Nam (phải gồm 10 chữ số và bắt đầu bằng 03, 05, 07, 08, 09).',
            'email.required' => 'Email không được để trống.',
            'email.email' => 'Email không đúng định dạng.',
            'email.max' => 'Email không được vượt quá 150 ký tự.',
            'email.unique' => 'Email này đã được sử dụng bởi tài khoản khác.',
            'avatar.image' => 'Avatar phải là file ảnh hợp lệ.',
            'avatar.max' => 'Avatar không được vượt quá 5MB.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
