<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Models\ContractTenant;
use App\Models\ContractVehicle;
use App\Models\Room;
use App\Models\Notification;
use App\Events\ContractExpired;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;

class CheckExpiredContracts extends Command
{
    protected $signature = 'contracts:check-expired';
    protected $description = 'Kiểm tra và tự động chuyển các hợp đồng đã hết thời hạn sang trạng thái hết hạn, đồng thời gửi thông báo realtime.';

    public function handle()
    {
        $today = now()->toDateString();

        // Tìm các hợp đồng ở trạng thái Đang hiệu lực (1) và có ngày kết thúc nhỏ hơn ngày hôm nay
        $expiredContracts = Contract::query()
            ->with(['room.building'])
            ->where('status', Contract::STATUS_ACTIVE)
            ->whereNotNull('end_date')
            ->where('end_date', '<', $today)
            ->get();

        if ($expiredContracts->isEmpty()) {
            $this->info('Không có hợp đồng nào hết hạn hôm nay.');
            return 0;
        }

        $this->info('Tìm thấy ' . $expiredContracts->count() . ' hợp đồng hết hạn. Bắt đầu xử lý...');

        foreach ($expiredContracts as $contract) {
            DB::transaction(function () use ($contract, $today) {
                // 1. Cập nhật trạng thái hợp đồng thành Hết hạn (2)
                $contract->status = Contract::STATUS_EXPIRED;
                $contract->save();

                // 2. Đóng thông tin khách thuê và phương tiện trong hợp đồng
                $endDateStr = $contract->end_date->toDateString();
                
                $contract->contractTenants()
                    ->where('is_staying', true)
                    ->get()
                    ->each(fn (ContractTenant $contractTenant) => $contractTenant->forceFill([
                        'leave_date' => $contractTenant->leave_date ?: $endDateStr,
                        'billing_end_date' => $contractTenant->billing_end_date ?: $endDateStr,
                        'is_staying' => false,
                    ])->save());

                $contract->contractVehicles()
                    ->where('is_active', true)
                    ->get()
                    ->each(fn (ContractVehicle $contractVehicle) => $contractVehicle->forceFill([
                        'ended_at' => $contractVehicle->ended_at ?: $endDateStr,
                        'billing_end_date' => $contractVehicle->billing_end_date ?: $endDateStr,
                        'is_active' => false,
                    ])->save());

                // 3. Cập nhật lại số người đang ở trong phòng
                if ($contract->room_id) {
                    $occupants = ContractTenant::query()
                        ->where('is_staying', true)
                        ->whereNull('leave_date')
                        ->whereHas('contract', fn (Builder $query) => $query->where('room_id', $contract->room_id)->where('status', Contract::STATUS_ACTIVE))
                        ->distinct('tenant_id')
                        ->count('tenant_id');

                    Room::query()->whereKey($contract->room_id)->update(['current_occupants' => $occupants]);
                }

                // 4. Tạo thông báo hệ thống lưu vào database cho Ban quản lý
                $notification = Notification::query()->create([
                    'title' => 'Hợp đồng hết hạn',
                    'content' => "Hợp đồng {$contract->contract_code} tại phòng " . ($contract->room?->room_number ?? 'không rõ') . " đã hết thời hạn.",
                    'notification_type' => Notification::NOTIFICATION_TYPE_SYSTEM,
                    'target_type' => Notification::TARGET_TYPE_ADMIN,
                    'building_id' => $contract->room?->building_id,
                    'room_id' => $contract->room_id,
                    'published_at' => now(),
                    'status' => Notification::STATUS_SENT,
                    'created_by' => null,
                ]);

                // 5. Bắn realtime thông báo qua Reverb
                broadcast(new ContractExpired($contract));
                
                $this->info("Hợp đồng {$contract->contract_code} đã được chuyển sang Hết hạn và phát broadcast thành công.");
            });
        }

        $this->info('Xử lý hoàn tất.');
        return 0;
    }
}
