<?php

namespace App\Http\Requests\Admin\Expense;

use App\Helpers\AdminScope;
use App\Helpers\ApiResponse;
use App\Models\Expense;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = $this->user('admin');

        if (! $admin) {
            return false;
        }

        return AdminScope::isSuperAdmin($admin) || AdminScope::isBuildingManager($admin);
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, 'Bạn không có quyền tạo phiếu chi', 403, null, 403)
        );
    }

    public function rules(): array
    {
        return [
            'building_id' => ['required', 'integer', Rule::exists('buildings', 'id')],
            'room_id' => ['nullable', 'integer', Rule::exists('rooms', 'id')],
            'expense_category_id' => ['nullable', 'integer', Rule::exists('expense_categories', 'id')->where('is_active', true)],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'regex:/^\d+(\.\d{1,2})?$/', 'gt:0'],
            'expense_date' => ['required', 'date_format:Y-m-d'],
            'receipt_images' => ['nullable', 'array', 'max:10'],
            'receipt_images.*' => ['required_with:receipt_images', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'payment_method' => ['nullable', 'integer', Rule::in(array_keys(Expense::PAYMENT_METHOD_LABELS))],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'building_id.required' => 'Vui lòng chọn tòa nhà cho phiếu chi.',
            'building_id.integer' => 'Tòa nhà không hợp lệ.',
            'building_id.exists' => 'Tòa nhà không tồn tại.',
            'room_id.integer' => 'Phòng không hợp lệ.',
            'room_id.exists' => 'Phòng không tồn tại.',
            'expense_category_id.integer' => 'Danh mục chi phí không hợp lệ.',
            'expense_category_id.exists' => 'Danh mục chi phí không tồn tại hoặc đã hết sử dụng.',
            'title.required' => 'Tiêu đề phiếu chi là bắt buộc.',
            'title.string' => 'Tiêu đề phiếu chi phải là chuỗi ký tự.',
            'title.max' => 'Tiêu đề phiếu chi không được vượt quá 255 ký tự.',
            'amount.required' => 'Số tiền chi là bắt buộc.',
            'amount.numeric' => 'Số tiền chi phải là số hợp lệ.',
            'amount.regex' => 'Số tiền chi phải là số hợp lệ và tối đa 2 chữ số thập phân.',
            'amount.gt' => 'Số tiền chi phải lớn hơn 0.',
            'expense_date.required' => 'Ngày chi là bắt buộc.',
            'expense_date.date_format' => 'Ngày chi phải đúng định dạng YYYY-MM-DD.',
            'receipt_images.array' => 'Danh sách ảnh chứng từ không hợp lệ.',
            'receipt_images.max' => 'Tối đa 10 ảnh chứng từ cho một phiếu chi.',
            'receipt_images.*.required_with' => 'Ảnh chứng từ không được bỏ trống.',
            'receipt_images.*.image' => 'Ảnh chứng từ phải là hình ảnh.',
            'receipt_images.*.mimes' => 'Ảnh chứng từ chỉ hỗ trợ jpg, jpeg, png hoặc webp.',
            'receipt_images.*.max' => 'Mỗi ảnh chứng từ không được vượt quá 5MB.',
            'payment_method.integer' => 'Phương thức thanh toán không hợp lệ.',
            'payment_method.in' => 'Phương thức thanh toán không nằm trong danh sách cho phép.',
            'note.string' => 'Ghi chú phiếu chi phải là chuỗi ký tự.',
            'note.max' => 'Ghi chú phiếu chi không được vượt quá 2000 ký tự.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
