<?php

namespace App\Http\Requests\Admin\RoomMovement;

use App\Helpers\ApiResponse;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateTransferDateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'movement_date' => ['required', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
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

            if ($movementDate->lt(now('Asia/Ho_Chi_Minh')->startOfDay())) {
                $validator->errors()->add('movement_date', 'Ngày chuyển phòng không được nhỏ hơn ngày hiện tại.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'movement_date.required' => 'Vui lòng chọn ngày chuyển phòng mới.',
            'movement_date.date_format' => 'Ngày chuyển phải đúng định dạng YYYY-MM-DD.',
            'note.max' => 'Ghi chú đổi ngày chuyển không được vượt quá 500 ký tự.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::responseJson(false, $validator->errors()->first(), 422, $validator->errors(), 422)
        );
    }
}
