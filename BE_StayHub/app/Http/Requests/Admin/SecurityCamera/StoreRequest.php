<?php

namespace App\Http\Requests\Admin\SecurityCamera;

use App\Helpers\ApiResponse;
use App\Models\SecurityCamera;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->filled('stream_url')) {
            $streamUrl = trim((string) $this->input('stream_url'));

            if (! preg_match('/^(https?|rtsp):\/\//i', $streamUrl)) {
                $streamUrl = 'http://' . ltrim($streamUrl, '/');
            }

            $this->merge(['stream_url' => $streamUrl]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'building_id' => [
                'required',
                'integer',
                Rule::exists('buildings', 'id')->where(function ($query) {
                    $query->where('status', \App\Models\Building::STATUS_ACTIVE);
                })
            ],
            'name' => ['required', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:160'],
            'source_type' => ['required', 'integer', Rule::in(array_keys(SecurityCamera::SOURCE_TYPE_LABELS))],
            'stream_url' => ['required', 'string', 'max:1000', 'regex:/^(https?|rtsp):\/\/.+/i'],
            'username' => ['nullable', 'string', 'max:120'],
            'password' => ['nullable', 'string', 'max:255'],
            'is_ai_enabled' => ['nullable', 'boolean'],
            'frame_interval_seconds' => ['nullable', 'integer', 'min:1', 'max:60'],
            'frames_per_batch' => ['nullable', 'integer', 'min:1', 'max:6'],
            'alert_cooldown_seconds' => ['nullable', 'integer', 'min:10', 'max:3600'],
            'status' => ['nullable', 'integer', Rule::in(array_keys(SecurityCamera::STATUS_LABELS))],
        ];
    }

    public function messages(): array
    {
        return [
            'building_id.required' => 'Vui lòng chọn tòa nhà cho camera.',
            'building_id.exists' => 'Tòa nhà không tồn tại hoặc không ở trạng thái hoạt động.',
            'name.required' => 'Tên camera là bắt buộc.',
            'name.max' => 'Tên camera không được vượt quá 120 ký tự.',
            'location.max' => 'Vị trí camera không được vượt quá 160 ký tự.',
            'source_type.required' => 'Loại nguồn camera là bắt buộc.',
            'source_type.in' => 'Loại nguồn camera không nằm trong danh sách cho phép.',
            'stream_url.required' => 'URL camera là bắt buộc.',
            'stream_url.regex' => 'URL camera phải là IP/domain hợp lệ, ví dụ 192.168.1.5:8081 hoặc http://192.168.1.5:8081.',
            'stream_url.max' => 'URL camera không được vượt quá 1000 ký tự.',
            'username.max' => 'Tên đăng nhập camera không được vượt quá 120 ký tự.',
            'password.max' => 'Mật khẩu camera không được vượt quá 255 ký tự.',
            'is_ai_enabled.boolean' => 'Trạng thái giám sát AI 24/24 không hợp lệ.',
            'frame_interval_seconds.min' => 'Khoảng cách quét tối thiểu là 1 giây.',
            'frame_interval_seconds.max' => 'Khoảng cách quét tối đa là 60 giây.',
            'frames_per_batch.min' => 'Số frame mỗi lần quét tối thiểu là 1.',
            'frames_per_batch.max' => 'Số frame mỗi lần quét tối đa là 6.',
            'alert_cooldown_seconds.min' => 'Cooldown cảnh báo tối thiểu là 10 giây.',
            'alert_cooldown_seconds.max' => 'Cooldown cảnh báo tối đa là 3600 giây.',
            'status.in' => 'Trạng thái camera không nằm trong danh sách cho phép.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
