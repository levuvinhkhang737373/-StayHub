<?php

namespace App\Http\Requests\Admin\Tenant;

use App\Helpers\ApiResponse;
use App\Models\Tenant;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->route('tenant');

        return [
            'created_by' => ['prohibited'],
            'password' => ['prohibited'],
            'full_name' => ['sometimes', 'required', 'string', 'max:150'],
            'gender' => ['nullable', 'integer', Rule::in(array_keys(Tenant::GENDER_LABELS))],
            'date_of_birth' => ['sometimes', 'required', 'date', 'before_or_equal:today'],
            'phone' => ['sometimes', 'required', 'string', 'max:30', Rule::unique('tenants', 'phone')->ignore($tenantId)],
            'email' => ['sometimes', 'required', 'email', 'max:150', Rule::unique('tenants', 'email')->ignore($tenantId)],
            'username' => ['sometimes', 'required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_.-]+$/', Rule::unique('tenants', 'username')->ignore($tenantId)],
            'permanent_address' => ['nullable', 'string', 'max:500'],
            'current_address' => ['nullable', 'string', 'max:500'],
            'avatar' => ['prohibited'],
            'avatar_url' => ['prohibited'],
            'status' => ['sometimes', 'required', 'integer', Rule::in(array_keys(Tenant::STATUS_LABELS))],
            'identity_type' => ['nullable', 'integer', Rule::in(array_keys(Tenant::IDENTITY_TYPE_LABELS))],
            'identity_number' => ['sometimes', 'required', 'string', 'max:30', Rule::unique('tenants', 'identity_number')->ignore($tenantId)],
            'front_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'back_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'delete_avatar' => ['prohibited'],
            'delete_front_image' => ['nullable', 'boolean'],
            'delete_back_image' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'created_by.prohibited' => 'Không được thay đổi người tạo khách thuê.',
            'password.prohibited' => 'Không được đổi mật khẩu từ màn CRUD khách thuê. Hãy dùng chức năng đặt lại mật khẩu riêng.',
            'full_name.required' => 'Họ tên khách thuê là bắt buộc khi cập nhật.',
            'full_name.string' => 'Họ tên khách thuê phải là chuỗi ký tự.',
            'full_name.max' => 'Họ tên khách thuê không được vượt quá 150 ký tự.',
            'gender.integer' => 'Giới tính khách thuê không hợp lệ.',
            'gender.in' => 'Giới tính khách thuê không nằm trong danh sách cho phép.',
            'date_of_birth.required' => 'Ngày sinh khách thuê là bắt buộc khi cập nhật.',
            'date_of_birth.date' => 'Ngày sinh khách thuê không hợp lệ.',
            'date_of_birth.before_or_equal' => 'Ngày sinh khách thuê không được lớn hơn ngày hiện tại.',
            'phone.required' => 'Số điện thoại khách thuê là bắt buộc khi cập nhật.',
            'phone.string' => 'Số điện thoại khách thuê phải là chuỗi ký tự.',
            'phone.max' => 'Số điện thoại khách thuê không được vượt quá 30 ký tự.',
            'phone.unique' => 'Số điện thoại khách thuê đã tồn tại.',
            'email.required' => 'Email khách thuê là bắt buộc khi cập nhật.',
            'email.email' => 'Email khách thuê không hợp lệ.',
            'email.max' => 'Email khách thuê không được vượt quá 150 ký tự.',
            'email.unique' => 'Email khách thuê đã tồn tại.',
            'username.required' => 'Tên đăng nhập khách thuê là bắt buộc khi cập nhật.',
            'username.string' => 'Tên đăng nhập khách thuê phải là chuỗi ký tự.',
            'username.max' => 'Tên đăng nhập khách thuê không được vượt quá 255 ký tự.',
            'username.regex' => 'Tên đăng nhập chỉ được chứa chữ, số, dấu gạch ngang, gạch dưới hoặc dấu chấm.',
            'username.unique' => 'Tên đăng nhập khách thuê đã tồn tại.',
            'permanent_address.string' => 'Địa chỉ thường trú phải là chuỗi ký tự.',
            'permanent_address.max' => 'Địa chỉ thường trú không được vượt quá 500 ký tự.',
            'current_address.string' => 'Địa chỉ hiện tại phải là chuỗi ký tự.',
            'current_address.max' => 'Địa chỉ hiện tại không được vượt quá 500 ký tự.',
            'avatar.prohibited' => 'Ảnh đại diện sẽ do khách thuê tự cập nhật ở giao diện khách thuê.',
            'avatar_url.prohibited' => 'Đường dẫn ảnh đại diện sẽ do khách thuê tự cập nhật ở giao diện khách thuê.',
            'status.required' => 'Trạng thái khách thuê là bắt buộc khi cập nhật.',
            'status.integer' => 'Trạng thái khách thuê không hợp lệ.',
            'status.in' => 'Trạng thái khách thuê không nằm trong danh sách cho phép.',
            'identity_type.integer' => 'Loại giấy tờ khách thuê không hợp lệ.',
            'identity_type.in' => 'Loại giấy tờ khách thuê không nằm trong danh sách cho phép.',
            'identity_number.required' => 'Số giấy tờ khách thuê là bắt buộc khi cập nhật.',
            'identity_number.string' => 'Số giấy tờ khách thuê phải là chuỗi ký tự.',
            'identity_number.max' => 'Số giấy tờ khách thuê không được vượt quá 30 ký tự.',
            'identity_number.unique' => 'Số giấy tờ khách thuê đã tồn tại.',
            'front_image.image' => 'Ảnh mặt trước giấy tờ phải là file ảnh hợp lệ.',
            'front_image.mimes' => 'Ảnh mặt trước giấy tờ chỉ hỗ trợ jpg, jpeg, png hoặc webp.',
            'front_image.max' => 'Ảnh mặt trước giấy tờ không được vượt quá 10MB.',
            'back_image.image' => 'Ảnh mặt sau giấy tờ phải là file ảnh hợp lệ.',
            'back_image.mimes' => 'Ảnh mặt sau giấy tờ chỉ hỗ trợ jpg, jpeg, png hoặc webp.',
            'back_image.max' => 'Ảnh mặt sau giấy tờ không được vượt quá 10MB.',
            'delete_avatar.prohibited' => 'Ảnh đại diện sẽ do khách thuê tự cập nhật ở giao diện khách thuê.',
            'delete_front_image.boolean' => 'Cờ xóa ảnh mặt trước giấy tờ không hợp lệ.',
            'delete_back_image.boolean' => 'Cờ xóa ảnh mặt sau giấy tờ không hợp lệ.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
