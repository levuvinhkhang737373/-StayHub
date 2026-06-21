<?php

namespace App\Http\Requests\Admin\Expense;

use App\Helpers\AdminScope;
use App\Helpers\ApiResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CancelRequest extends FormRequest
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
            ApiResponse::responseJson(false, 'Bạn không có quyền hủy phiếu chi', 403, null, 403)
        );
    }

    public function rules(): array
    {
        return [];
    }
}
