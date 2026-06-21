<?php

namespace App\Http\Resources\Admin;

use App\Helpers\DecimalMoney;
use App\Helpers\ImageHelper;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $receiptImages = $this->receipt_images ?? [];

        return [
            'id' => $this->id,
            'expense_code' => $this->expense_code,
            'building_id' => $this->building_id,
            'building' => $this->whenLoaded('building', fn (): ?array => $this->building ? [
                'id' => $this->building->id,
                'name' => $this->building->name,
                'manager_admin_id' => $this->building->manager_admin_id,
            ] : null),
            'room_id' => $this->room_id,
            'room' => $this->whenLoaded('room', fn (): ?array => $this->room ? [
                'id' => $this->room->id,
                'building_id' => $this->room->building_id,
                'room_number' => $this->room->room_number,
                'floor' => $this->room->floor,
                'status' => $this->room->status,
            ] : null),
            'expense_category_id' => $this->expense_category_id,
            'category' => $this->whenLoaded('category', fn (): ?array => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'is_active' => (bool) $this->category->is_active,
            ] : null),
            'title' => $this->title,
            'amount' => $this->amount,
            'amount_formatted' => $this->formatVnd($this->amount),
            'expense_date' => optional($this->expense_date)->toDateString(),
            'receipt_images' => $receiptImages,
            'receipt_image_urls' => collect($receiptImages)->map(fn (string $path): string => ImageHelper::load($path))->values()->all(),
            'payment_method' => $this->payment_method,
            'payment_method_label' => Expense::PAYMENT_METHOD_LABELS[$this->payment_method] ?? null,
            'note' => $this->note,
            'status' => $this->status,
            'status_label' => Expense::STATUS_LABELS[$this->status] ?? null,
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', fn (): ?array => $this->creator ? [
                'id' => $this->creator->id,
                'full_name' => $this->creator->full_name,
            ] : null),
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }

    private function formatVnd(mixed $amount): string
    {
        $normalizedAmount = DecimalMoney::normalize($amount);
        $integerAmount = explode('.', $normalizedAmount, 2)[0];

        return number_format((int) $integerAmount, 0, ',', '.').' VNĐ';
    }
}
