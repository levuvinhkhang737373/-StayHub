<?php

namespace App\Http\Requests\Admin\Room;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;


class RoomRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'building_id'          => 'required|integer|exists:buildings,id',
            'room_type_id'         => 'required|integer|exists:room_types,id',
            'room_number'          => 'required|string|max:50',
            'floor'                => 'required|integer|min:0',
            'area_m2'              => 'required|numeric|min:0',
            'base_price'           => 'required|numeric|min:0',
            'max_occupants'        => 'required|integer|min:1',
            'description'          => 'nullable|string',
            'status'               => 'nullable|integer|in:1,2,3',
            'images'               => 'nullable|array',
            'images.*'             => 'image|mimes:jpeg,jpg,png,webp|max:10240',
            'assets'               => 'nullable|array',
            'assets.*.template_id' => 'required|integer|exists:asset_templates,id',
            'assets.*.quantity'    => 'required|integer|min:1',
            'assets.*.note'        => 'nullable|string|max:255',
        ];
    }

    public function messages()
    {
        return [
            'building_id.required'          => 'Vui lòng chọn tòa nhà.',
            'building_id.integer'           => 'Mã tòa nhà phải là số nguyên.',
            'building_id.exists'            => 'Tòa nhà được chọn không tồn tại trên hệ thống.',

            'room_type_id.required'         => 'Vui lòng chọn loại phòng trọ.',
            'room_type_id.integer'          => 'Mã loại phòng phải là số nguyên.',
            'room_type_id.exists'           => 'Loại phòng được chọn không hợp lệ.',

            'room_number.required'          => 'Vui lòng nhập số/tên phòng.',
            'room_number.string'            => 'Số phòng phải là một chuỗi ký tự.',
            'room_number.max'               => 'Số phòng không được vượt quá 50 ký tự.',

            'floor.required'                => 'Vui lòng nhập số tầng.',
            'floor.integer'                 => 'Số tầng phải là số nguyên.',
            'floor.min'                     => 'Số tầng không được nhỏ hơn 0 (Tầng trệt).',

            'area_m2.required'              => 'Vui lòng nhập diện tích phòng.',
            'area_m2.numeric'               => 'Diện tích phòng phải là một số hợp lệ.',
            'area_m2.min'                   => 'Diện tích phòng không được nhỏ hơn 0 m².',

            'base_price.required'           => 'Vui lòng nhập giá phòng cơ bản.',
            'base_price.numeric'            => 'Giá phòng phải là một số tiền hợp lệ.',
            'base_price.min'                => 'Giá phòng không được nhỏ hơn 0 VNĐ.',

            'max_occupants.required'        => 'Vui lòng nhập số người ở tối đa.',
            'max_occupants.integer'         => 'Số người ở tối đa phải là số nguyên.',
            'max_occupants.min'             => 'Số người ở tối đa phải từ 1 người trở lên.',

            'status.integer'                => 'Trạng thái phòng phải là mã số nguyên.',
            'status.in'                     => 'Trạng thái phòng được chọn không hợp lệ (Chỉ chấp nhận Hoạt động, Bảo trì, Ngưng sử dụng).',

            'images.array'                  => 'Dữ liệu hình ảnh tải lên phải là một danh sách file.',
            'images.*.image'                => 'Tập tin tải lên bắt buộc phải là định dạng ảnh.',
            'images.*.mimes'                => 'Ảnh phòng chỉ chấp nhận các định dạng: jpeg, jpg, png, webp.',
            'images.*.max'                  => 'Dung lượng mỗi ảnh không được vượt quá 10MB.',


            'assets.array'                  => 'Danh sách tài sản gửi lên phải là định dạng mảng.',
            'assets.*.template_id.required' => 'Mã tài sản mẫu là bắt buộc.',
            'assets.*.template_id.integer'  => 'Mã tài sản mẫu phải là số nguyên.',
            'assets.*.template_id.exists'   => 'Tài sản mẫu được chọn không tồn tại trong hệ thống.',

            'assets.*.quantity.required'    => 'Vui lòng nhập số lượng cho tài sản.',
            'assets.*.quantity.integer'     => 'Số lượng tài sản phải là một số nguyên.',
            'assets.*.quantity.min'         => 'Số lượng tài sản được chọn ít nhất phải từ 1 trở lên.',

            'assets.*.note.string'          => 'Ghi chú tài sản phải là chuỗi ký tự.',
            'assets.*.note.max'             => 'Ghi chú tài sản không được dài quá 255 ký tự.',
        ];
    }
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
