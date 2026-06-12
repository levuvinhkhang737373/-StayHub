<?php

namespace App\Http\Requests\Admin\Notification;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'notification_type' => ['required', 'integer', 'in:1,2,3,4,5'],
            'target_type' => ['required', 'integer', 'in:1,2,3,4'],
            'building_id' => ['nullable', 'integer', 'exists:buildings,id', 'required_if:target_type,2'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id', 'required_if:target_type,3'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id', 'required_if:target_type,4'],
            'status' => ['required', 'integer', 'in:1,2,3'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Tiêu đề thông báo là bắt buộc.',
            'title.string' => 'Tiêu đề phải là chuỗi ký tự.',
            'title.max' => 'Tiêu đề không được vượt quá 255 ký tự.',
            'content.required' => 'Nội dung thông báo là bắt buộc.',
            'notification_type.required' => 'Loại thông báo là bắt buộc.',
            'notification_type.in' => 'Loại thông báo không hợp lệ.',
            'target_type.required' => 'Đối tượng nhận thông báo là bắt buộc.',
            'target_type.in' => 'Đối tượng nhận không hợp lệ.',
            'building_id.required_if' => 'Vui lòng chọn tòa nhà cần gửi thông báo.',
            'building_id.exists' => 'Tòa nhà được chọn không tồn tại.',
            'room_id.required_if' => 'Vui lòng chọn phòng cần gửi thông báo.',
            'room_id.exists' => 'Phòng được chọn không tồn tại.',
            'tenant_id.required_if' => 'Vui lòng chọn khách thuê cần gửi thông báo.',
            'tenant_id.exists' => 'Khách thuê được chọn không tồn tại.',
            'status.required' => 'Trạng thái phát thông báo là bắt buộc.',
            'status.in' => 'Trạng thái phát không hợp lệ.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
