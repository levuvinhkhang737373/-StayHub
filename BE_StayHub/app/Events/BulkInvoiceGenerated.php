<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BulkInvoiceGenerated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $buildingId;
    public int $billingMonth;
    public int $billingYear;
    public int $successCount;
    public int $errorCount;
    public int $adminId;

    public function __construct(int $buildingId, int $billingMonth, int $billingYear, int $successCount, int $errorCount, int $adminId)
    {
        $this->buildingId = $buildingId;
        $this->billingMonth = $billingMonth;
        $this->billingYear = $billingYear;
        $this->successCount = $successCount;
        $this->errorCount = $errorCount;
        $this->adminId = $adminId;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('admin.invoices.building.' . $this->buildingId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'BulkInvoiceGenerated';
    }

    public function broadcastWith(): array
    {
        return [
            'building_id' => $this->buildingId,
            'billing_month' => $this->billingMonth,
            'billing_year' => $this->billingYear,
            'success_count' => $this->successCount,
            'error_count' => $this->errorCount,
            'message' => "Tạo hàng loạt hóa đơn hoàn tất: {$this->successCount} thành công, {$this->errorCount} thất bại.",
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
