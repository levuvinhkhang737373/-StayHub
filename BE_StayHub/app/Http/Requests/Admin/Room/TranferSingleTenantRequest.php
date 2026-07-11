<?php

namespace App\Http\Requests\Admin\Room;

use App\Helpers\ApiResponse;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class TranferSingleTenantRequest extends FormRequest
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
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'tenant_ids' => ['required_without:tenant_id', 'array', 'min:1', 'max:50'],
            'tenant_ids.*' => ['required', 'integer', 'distinct', Rule::exists('tenants', 'id')],
            'to_room_id' => [
                'required',
                'integer',
                Rule::exists('rooms', 'id')->where(function ($query) {
                    $query->where('status', \App\Models\Room::STATUS_ACTIVE);
                })
            ],
            'movement_date' => ['required', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:500'],

            'deposit_deduction_amount' => ['nullable', 'numeric', 'min:0'],
            'new_deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'deduction_items' => ['nullable', 'array'],
            'deduction_items.*.name' => ['required_with:deduction_items', 'string', 'max:150'],
            'deduction_items.*.amount' => ['required_with:deduction_items', 'numeric', 'min:0'],
            'deduction_items.*.note' => ['nullable', 'string', 'max:255'],
            'transfer_fee' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('tenant_ids') && $this->filled('tenant_id')) {
            $this->merge(['tenant_ids' => [(int) $this->input('tenant_id')]]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('movement_date')) {
                return;
            }

            try {
                $movementDate = Carbon::createFromFormat('Y-m-d', (string) $this->input('movement_date'))->startOfDay();
            } catch (\Exception) {
                return;
            }

            $minimumDate = now('Asia/Ho_Chi_Minh')->startOfDay()->addDay();

            if ($movementDate->lt($minimumDate)) {
                $validator->errors()->add('movement_date', 'Ngày chuyển phòng phải từ ngày tiếp theo trở đi. Nếu muốn chuyển sang phòng khác ngay lập tức, vui lòng thực hiện thanh lý hợp đồng.');
            }
        });
    }
    public function messages(): array
    {
        return [
            'tenant_id.required' => 'Vui lòng chọn khách thuê cần chuyển.',
            'tenant_ids.required_without' => 'Vui lòng chọn khách thuê cần chuyển.',
            'tenant_ids.array' => 'Danh sách khách thuê cần chuyển không hợp lệ.',
            'tenant_ids.*.distinct' => 'Danh sách khách thuê cần chuyển không được trùng nhau.',
            'to_room_id.required' => 'Vui lòng chọn phòng đích.',
            'to_room_id.exists' => 'Phòng đích không tồn tại hoặc không ở trạng thái hoạt động.',
            'movement_date.date_format' => 'Ngày chuyển phải đúng định dạng YYYY-MM-DD.',
            'new_deposit_amount.min' => 'Tiền cọc yêu cầu của hợp đồng mới không được âm.',
            'deduction_items.*.name.required_with' => 'Vui lòng nhập tên khoản khấu trừ.',
            'deduction_items.*.amount.required_with' => 'Vui lòng nhập số tiền khấu trừ.',
        ];
    }
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
