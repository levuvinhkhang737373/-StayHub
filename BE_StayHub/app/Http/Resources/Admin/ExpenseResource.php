<?php

namespace App\Http\Resources\Admin;

use App\Helpers\DecimalMoney;
use App\Helpers\ImageHelper;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $receiptImages = $this->receipt_images ?? [];

        return [
            'id' => $this->id,
            'expense_code' => $this->expense_code,
            'building_id' => $this->building_id,
            'building_name' => $this->whenLoaded('building', fn (): ?string => $this->building?->name),
            'room_id' => $this->room_id,
            'room_number' => $this->whenLoaded('room', fn (): ?string => $this->room?->room_number),
            'expense_category_id' => $this->expense_category_id,
            'category_name' => $this->whenLoaded('category', fn (): ?string => $this->category?->name),
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
            'creator_name' => $this->whenLoaded('creator', fn (): ?string => $this->creator?->full_name),
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
