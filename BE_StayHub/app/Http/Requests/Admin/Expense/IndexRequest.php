<?php

namespace App\Http\Requests\Admin\Expense;

use App\Helpers\AdminScope;
use App\Helpers\ApiResponse;
use App\Models\Expense;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class IndexRequest extends FormRequest
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
            ApiResponse::responseJson(false, 'Bạn không có quyền xem phiếu chi', 403, null, 403)
        );
    }

    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:120'],
            'building_id' => ['nullable', 'integer', Rule::exists('buildings', 'id')],
            'room_id' => ['nullable', 'integer', Rule::exists('rooms', 'id')],
            'expense_category_id' => ['nullable', 'integer', Rule::exists('expense_categories', 'id')],
            'payment_method' => ['nullable', 'integer', Rule::in(array_keys(Expense::PAYMENT_METHOD_LABELS))],
            'status' => ['nullable', 'integer', Rule::in(array_keys(Expense::STATUS_LABELS))],
            'expense_date_from' => ['nullable', 'date_format:Y-m-d'],
            'expense_date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:expense_date_from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'keyword.string' => 'Từ khóa tìm kiếm phiếu chi phải là chuỗi ký tự.',
            'keyword.max' => 'Từ khóa tìm kiếm phiếu chi không được vượt quá 120 ký tự.',
            'building_id.integer' => 'Tòa nhà không hợp lệ.',
            'building_id.exists' => 'Tòa nhà không tồn tại.',
            'room_id.integer' => 'Phòng không hợp lệ.',
            'room_id.exists' => 'Phòng không tồn tại.',
            'expense_category_id.integer' => 'Danh mục chi phí không hợp lệ.',
            'expense_category_id.exists' => 'Danh mục chi phí không tồn tại.',
            'payment_method.integer' => 'Phương thức thanh toán không hợp lệ.',
            'payment_method.in' => 'Phương thức thanh toán không nằm trong danh sách cho phép.',
            'status.integer' => 'Trạng thái phiếu chi không hợp lệ.',
            'status.in' => 'Trạng thái phiếu chi không nằm trong danh sách cho phép.',
            'expense_date_from.date_format' => 'Ngày chi từ phải đúng định dạng YYYY-MM-DD.',
            'expense_date_to.date_format' => 'Ngày chi đến phải đúng định dạng YYYY-MM-DD.',
            'expense_date_to.after_or_equal' => 'Ngày chi đến phải lớn hơn hoặc bằng ngày chi từ.',
            'page.integer' => 'Trang hiện tại không hợp lệ.',
            'page.min' => 'Trang hiện tại tối thiểu là 1.',
            'per_page.integer' => 'Số lượng phiếu chi mỗi trang không hợp lệ.',
            'per_page.min' => 'Số lượng phiếu chi mỗi trang tối thiểu là 1.',
            'per_page.max' => 'Số lượng phiếu chi mỗi trang tối đa là 100.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
