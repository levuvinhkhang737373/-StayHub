<?php

namespace App\Http\Requests\Admin\Building;

use App\Helpers\ApiResponse;
use App\Models\Admin;
use App\Models\Building;
use App\Models\BuildingImage;
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
        return [
            'region_id' => ['sometimes', 'required', 'integer', 'exists:regions,id'],
            'manager_admin_id' => [
                'nullable',
                'integer',
                Rule::exists('admins', 'id')->where('role', Admin::ROLE_BUILDING_MANAGER)->where('status', Admin::STATUS_ACTIVE),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
            'total_floors' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'gender_policy' => ['nullable', 'integer', Rule::in(array_keys(Building::GENDER_POLICY_LABELS))],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'integer', Rule::in(array_keys(Building::STATUS_LABELS))],
            'created_by' => ['nullable', 'integer', 'exists:admins,id'],
            'images' => ['nullable', 'array', 'max:20'],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'image_metadata' => ['nullable', 'array'],
            'image_metadata.*.is_primary' => ['nullable', 'boolean'],
            'image_metadata.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'image_metadata.*.status' => ['nullable', 'integer', Rule::in(array_keys(BuildingImage::STATUS_LABELS))],
            'delete_image_ids' => ['nullable', 'array', 'max:100'],
            'delete_image_ids.*' => ['required', 'integer', 'distinct', Rule::exists('building_images', 'id')->where('building_id', $this->route('building'))],
            'primary_image_id' => ['nullable', 'integer', Rule::exists('building_images', 'id')->where('building_id', $this->route('building'))],
        ];
    }

    public function messages(): array
    {
        return [
            'region_id.required' => 'Khu vực của tòa nhà là bắt buộc khi cập nhật.',
            'region_id.integer' => 'Khu vực của tòa nhà không hợp lệ.',
            'region_id.exists' => 'Khu vực của tòa nhà không tồn tại.',
            'manager_admin_id.integer' => 'Quản lý tòa nhà không hợp lệ.',
            'manager_admin_id.exists' => 'Quản lý tòa nhà phải là quản lý đang hoạt động trong hệ thống.',
            'name.required' => 'Tên tòa nhà là bắt buộc khi cập nhật.',
            'name.string' => 'Tên tòa nhà phải là chuỗi ký tự.',
            'name.max' => 'Tên tòa nhà không được vượt quá 150 ký tự.',
            'address.string' => 'Địa chỉ tòa nhà phải là chuỗi ký tự.',
            'address.max' => 'Địa chỉ tòa nhà không được vượt quá 500 ký tự.',
            'total_floors.integer' => 'Tổng số tầng phải là số nguyên.',
            'total_floors.min' => 'Tổng số tầng phải lớn hơn hoặc bằng 1.',
            'total_floors.max' => 'Tổng số tầng không được vượt quá 1000.',
            'gender_policy.integer' => 'Chính sách giới tính của tòa nhà không hợp lệ.',
            'gender_policy.in' => 'Chính sách giới tính của tòa nhà không nằm trong danh sách cho phép.',
            'description.string' => 'Mô tả tòa nhà phải là chuỗi ký tự.',
            'status.integer' => 'Trạng thái tòa nhà không hợp lệ.',
            'status.in' => 'Trạng thái tòa nhà không nằm trong danh sách cho phép.',
            'created_by.integer' => 'Người tạo tòa nhà không hợp lệ.',
            'created_by.exists' => 'Người tạo tòa nhà không tồn tại.',
            'images.array' => 'Danh sách ảnh tòa nhà không hợp lệ.',
            'images.max' => 'Mỗi lần chỉ được tải lên tối đa 20 ảnh tòa nhà.',
            'images.*.required' => 'File ảnh tòa nhà là bắt buộc.',
            'images.*.image' => 'File tải lên phải là ảnh hợp lệ.',
            'images.*.mimes' => 'Ảnh tòa nhà chỉ hỗ trợ định dạng jpg, jpeg, png hoặc webp.',
            'images.*.max' => 'Dung lượng mỗi ảnh tòa nhà không được vượt quá 10MB.',
            'image_metadata.array' => 'Thông tin cấu hình ảnh tòa nhà không hợp lệ.',
            'image_metadata.*.is_primary.boolean' => 'Trạng thái ảnh đại diện không hợp lệ.',
            'image_metadata.*.sort_order.integer' => 'Thứ tự ảnh tòa nhà phải là số nguyên.',
            'image_metadata.*.sort_order.min' => 'Thứ tự ảnh tòa nhà phải lớn hơn hoặc bằng 0.',
            'image_metadata.*.sort_order.max' => 'Thứ tự ảnh tòa nhà không được vượt quá 100000.',
            'image_metadata.*.status.integer' => 'Trạng thái ảnh tòa nhà không hợp lệ.',
            'image_metadata.*.status.in' => 'Trạng thái ảnh tòa nhà không nằm trong danh sách cho phép.',
            'delete_image_ids.array' => 'Danh sách ảnh cần xóa không hợp lệ.',
            'delete_image_ids.max' => 'Mỗi lần chỉ được xóa tối đa 100 ảnh tòa nhà.',
            'delete_image_ids.*.required' => 'ID ảnh tòa nhà cần xóa là bắt buộc.',
            'delete_image_ids.*.integer' => 'ID ảnh tòa nhà cần xóa không hợp lệ.',
            'delete_image_ids.*.distinct' => 'Danh sách ảnh cần xóa không được trùng lặp.',
            'delete_image_ids.*.exists' => 'Ảnh tòa nhà cần xóa không tồn tại.',
            'primary_image_id.integer' => 'Ảnh đại diện tòa nhà không hợp lệ.',
            'primary_image_id.exists' => 'Ảnh đại diện tòa nhà không tồn tại.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
