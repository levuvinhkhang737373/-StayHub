<?php

namespace App\Http\Requests\Admin\AdminAccount;

use App\Helpers\ApiResponse;
use App\Models\Admin;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:255', Rule::unique('admins', 'username')],
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('admins', 'email')],
            'phone' => ['required', 'string', 'max:20', Rule::unique('admins', 'phone')],
            'password' => ['prohibited'],
            'role' => ['required', 'integer', Rule::in(array_keys(Admin::ROLE_LABELS))],
            'status' => ['nullable', 'integer', Rule::in(array_keys(Admin::STATUS_LABELS))],
            'gender' => ['nullable', 'integer', Rule::in(array_keys(Admin::GENDER_LABELS))],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'address' => ['nullable', 'string', 'max:500'],
            'avatar_url' => ['nullable', 'string', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.required' => 'Tên đăng nhập là bắt buộc.',
            'username.string' => 'Tên đăng nhập phải là chuỗi ký tự.',
            'username.max' => 'Tên đăng nhập không được vượt quá 255 ký tự.',
            'username.unique' => 'Tên đăng nhập đã tồn tại.',
            'full_name.required' => 'Họ tên admin là bắt buộc.',
            'full_name.string' => 'Họ tên admin phải là chuỗi ký tự.',
            'full_name.max' => 'Họ tên admin không được vượt quá 255 ký tự.',
            'email.required' => 'Email admin là bắt buộc.',
            'email.email' => 'Email admin không hợp lệ.',
            'email.max' => 'Email admin không được vượt quá 255 ký tự.',
            'email.unique' => 'Email admin đã tồn tại.',
            'phone.required' => 'Số điện thoại admin là bắt buộc.',
            'phone.string' => 'Số điện thoại admin phải là chuỗi ký tự.',
            'phone.max' => 'Số điện thoại admin không được vượt quá 20 ký tự.',
            'phone.unique' => 'Số điện thoại admin đã tồn tại.',
            'password.prohibited' => 'Mật khẩu sẽ được hệ thống tự tạo và gửi qua email, vui lòng không truyền mật khẩu khi tạo tài khoản.',
            'role.required' => 'Vai trò admin là bắt buộc.',
            'role.integer' => 'Vai trò admin không hợp lệ.',
            'role.in' => 'Vai trò admin không nằm trong danh sách cho phép.',
            'status.integer' => 'Trạng thái admin không hợp lệ.',
            'status.in' => 'Trạng thái admin không nằm trong danh sách cho phép.',
            'gender.integer' => 'Giới tính admin không hợp lệ.',
            'gender.in' => 'Giới tính admin không nằm trong danh sách cho phép.',
            'date_of_birth.date' => 'Ngày sinh admin không hợp lệ.',
            'date_of_birth.before_or_equal' => 'Ngày sinh admin không được lớn hơn ngày hiện tại.',
            'address.string' => 'Địa chỉ admin phải là chuỗi ký tự.',
            'address.max' => 'Địa chỉ admin không được vượt quá 500 ký tự.',
            'avatar_url.string' => 'Đường dẫn ảnh đại diện phải là chuỗi ký tự.',
            'avatar_url.max' => 'Đường dẫn ảnh đại diện không được vượt quá 2048 ký tự.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
