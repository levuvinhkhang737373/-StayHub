<?php

namespace App\Services\Invoice;

use App\Helpers\DecimalMoney;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\InvoiceDebtRollover;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class InvoiceDebtRolloverService
{
    // Lấy danh sách các khoản nợ chuyển tiếp từ kỳ trước
    public function previousDebtRollovers(Contract $contract, int $billingYear, int $billingMonth, ?int $targetInvoiceId = null, bool $lock = false): Collection
    {
        $query = Invoice::query()
            ->with(['debtRolloversOut.targetInvoice:id,invoice_code,status'])
            ->where('contract_id', $contract->id)
            ->whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIALLY_PAID, Invoice::STATUS_OVERDUE])
            ->where('remaining_amount', '>', 0)
            ->where(function (Builder $query) use ($billingYear, $billingMonth): void {
                $query->where('billing_year', '<', $billingYear)
                    ->orWhere(function (Builder $sameYearQuery) use ($billingYear, $billingMonth): void {
                        $sameYearQuery->where('billing_year', $billingYear)
                            ->where('billing_month', '<', $billingMonth);
                    });
            })
            ->orderBy('billing_year')
            ->orderBy('billing_month')
            ->orderBy('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get()
            ->map(function (Invoice $invoice) use ($targetInvoiceId): ?array {
                $amount = $this->collectibleRemainingAmount($invoice, $targetInvoiceId);

                if (! DecimalMoney::isPositive($amount)) {
                    return null;
                }

                return [
                    'source_invoice_id' => (int) $invoice->id,
                    'source_invoice_code' => $invoice->invoice_code,
                    'billing_year' => (int) $invoice->billing_year,
                    'billing_month' => (int) $invoice->billing_month,
                    'amount' => $amount,
                ];
            })
            ->filter()
            ->values();
    }

    // Tính tổng số tiền nợ chuyển tiếp từ kỳ trước
    public function previousDebtAmount(Collection|array $rollovers): string
    {
        return DecimalMoney::add(collect($rollovers)
            ->map(fn (array $rollover): string => (string) ($rollover['amount'] ?? '0'))
            ->all());
    }

    // Đồng bộ nợ chuyển tiếp đích cho hóa đơn mới
    public function syncTargetRollovers(Invoice $targetInvoice, Collection|array $rollovers): void
    {
        $wantedRollovers = collect($rollovers)
            ->filter(fn (array $rollover): bool => DecimalMoney::isPositive($rollover['amount'] ?? '0'))
            ->keyBy(fn (array $rollover): int => (int) $rollover['source_invoice_id']);

        $existingRollovers = InvoiceDebtRollover::query()
            ->where('target_invoice_id', $targetInvoice->id)
            ->lockForUpdate()
            ->get()
            ->keyBy('source_invoice_id');

        $hasSettledRollover = $existingRollovers->contains(
            fn (InvoiceDebtRollover $rollover): bool => DecimalMoney::isPositive($rollover->settled_amount)
                || (int) $rollover->status === InvoiceDebtRollover::STATUS_SETTLED
        );

        if ($hasSettledRollover) {
            throw new \DomainException('Không thể đồng bộ công nợ cũ vì hóa đơn đã phát sinh phân bổ thanh toán');
        }

        foreach ($existingRollovers as $sourceInvoiceId => $rollover) {
            if ($wantedRollovers->has((int) $sourceInvoiceId)) {
                continue;
            }

            $rollover->forceFill([
                'settled_amount' => '0.00',
                'status' => InvoiceDebtRollover::STATUS_CANCELLED,
            ])->save();
        }

        foreach ($wantedRollovers as $sourceInvoiceId => $wantedRollover) {
            $rollover = $existingRollovers->get((int) $sourceInvoiceId) ?: new InvoiceDebtRollover([
                'source_invoice_id' => (int) $sourceInvoiceId,
                'target_invoice_id' => (int) $targetInvoice->id,
            ]);

            $rollover->forceFill([
                'source_invoice_id' => (int) $sourceInvoiceId,
                'target_invoice_id' => (int) $targetInvoice->id,
                'amount' => DecimalMoney::normalize($wantedRollover['amount']),
                'settled_amount' => '0.00',
                'status' => InvoiceDebtRollover::STATUS_ACTIVE,
            ])->save();
        }
    }

    // Hủy các khoản nợ chuyển tiếp đích của hóa đơn
    public function cancelTargetRollovers(Invoice $targetInvoice): void
    {
        $rollovers = InvoiceDebtRollover::query()
            ->where('target_invoice_id', $targetInvoice->id)
            ->lockForUpdate()
            ->get();

        $hasSettledRollover = $rollovers->contains(
            fn (InvoiceDebtRollover $rollover): bool => DecimalMoney::isPositive($rollover->settled_amount)
                || (int) $rollover->status === InvoiceDebtRollover::STATUS_SETTLED
        );

        if ($hasSettledRollover) {
            throw new \DomainException('Không thể hủy chuyển nợ vì hóa đơn đã phát sinh phân bổ thanh toán');
        }

        $rollovers->each(fn (InvoiceDebtRollover $rollover): bool => $rollover->forceFill([
            'settled_amount' => '0.00',
            'status' => InvoiceDebtRollover::STATUS_CANCELLED,
        ])->save());
    }

    // Kiểm tra khoản nợ chuyển tiếp đi có hoạt động không
    public function activeRolloverOut(Invoice $sourceInvoice): ?InvoiceDebtRollover
    {
        return InvoiceDebtRollover::query()
            ->with('targetInvoice:id,invoice_code,status,billing_year,billing_month')
            ->where('source_invoice_id', $sourceInvoice->id)
            ->where('status', InvoiceDebtRollover::STATUS_ACTIVE)
            ->whereRaw('settled_amount < amount')
            ->whereHas('targetInvoice', fn (Builder $query): Builder => $query->where('status', '!=', Invoice::STATUS_CANCELLED))
            ->orderBy('id')
            ->first();
    }

    // Mở lại khoản nợ chuyển tiếp đi để tiếp tục thu
    public function openRolloverOut(Invoice $sourceInvoice): ?InvoiceDebtRollover
    {
        return InvoiceDebtRollover::query()
            ->with('targetInvoice:id,invoice_code,status,billing_year,billing_month')
            ->where('source_invoice_id', $sourceInvoice->id)
            ->whereIn('status', [InvoiceDebtRollover::STATUS_ACTIVE, InvoiceDebtRollover::STATUS_SETTLED])
            ->whereHas('targetInvoice', fn (Builder $query): Builder => $query->where('status', '!=', Invoice::STATUS_CANCELLED))
            ->orderBy('id')
            ->first();
    }

    // Xác định hóa đơn gốc của khoản nợ chuyển tiếp đến
    public function payableInvoiceForIncomingInvoice(Invoice $invoice): Invoice
    {
        $payableInvoice = $invoice;
        $visitedInvoiceIds = [];

        while (true) {
            if (in_array((int) $payableInvoice->id, $visitedInvoiceIds, true)) {
                return $payableInvoice;
            }

            $visitedInvoiceIds[] = (int) $payableInvoice->id;
            $rollover = $this->activeRolloverOut($payableInvoice);

            if (! $rollover?->targetInvoice) {
                return $payableInvoice;
            }

            $payableInvoice = $rollover->targetInvoice;
        }
    }

    // Kiểm tra hóa đơn đã được chuyển tiếp nợ hay chưa
    public function isInvoiceRolledOver(Invoice $invoice): bool
    {
        return $this->activeRolloverOut($invoice) !== null;
    }

    // Tính số tiền còn lại có thể thu của hóa đơn
    public function collectibleRemainingAmount(Invoice $invoice, ?int $excludedTargetInvoiceId = null): string
    {
        $activeRolloverAmount = $this->activeRolloverOutAmount($invoice, $excludedTargetInvoiceId);

        return DecimalMoney::maxZero(DecimalMoney::subtract($invoice->remaining_amount, $activeRolloverAmount));
    }

    // Phân bổ số tiền đã thanh toán cho các khoản nợ chuyển tiếp
    public function allocateConfirmedPaymentToDebtRollovers(Invoice $targetInvoice, Payment $realPayment): void
    {
        if ((int) $realPayment->status !== Payment::STATUS_CONFIRMED) {
            return;
        }

        $alreadyAllocated = Payment::query()
            ->where('allocated_from_payment_id', $realPayment->id)
            ->where('is_internal_allocation', true)
            ->exists();

        if ($alreadyAllocated) {
            return;
        }

        $remainingPaymentAmount = DecimalMoney::normalize($realPayment->amount);
        if (! DecimalMoney::isPositive($remainingPaymentAmount)) {
            return;
        }

        $rollovers = InvoiceDebtRollover::query()
            ->with('sourceInvoice')
            ->where('target_invoice_id', $targetInvoice->id)
            ->where('status', InvoiceDebtRollover::STATUS_ACTIVE)
            ->whereRaw('settled_amount < amount')
            ->lockForUpdate()
            ->get()
            ->sortBy(fn (InvoiceDebtRollover $rollover): string => sprintf(
                '%04d-%02d-%010d',
                (int) $rollover->sourceInvoice?->billing_year,
                (int) $rollover->sourceInvoice?->billing_month,
                (int) $rollover->source_invoice_id
            ));

        foreach ($rollovers as $rollover) {
            if (! DecimalMoney::isPositive($remainingPaymentAmount)) {
                break;
            }

            $sourceInvoice = Invoice::query()
                ->lockForUpdate()
                ->find($rollover->source_invoice_id);

            if (! $sourceInvoice || (int) $sourceInvoice->status === Invoice::STATUS_CANCELLED) {
                continue;
            }

            $rolloverRemaining = DecimalMoney::maxZero(DecimalMoney::subtract($rollover->amount, $rollover->settled_amount));
            $sourceRemaining = DecimalMoney::maxZero($sourceInvoice->remaining_amount);
            $allocationAmount = DecimalMoney::min(DecimalMoney::min($remainingPaymentAmount, $rolloverRemaining), $sourceRemaining);

            if (! DecimalMoney::isPositive($allocationAmount)) {
                $this->settleRolloverIfSourceCleared($rollover, $sourceInvoice);

                continue;
            }

            $internalPayment = Payment::query()->create([
                'payment_code' => $this->makePaymentCode(),
                'invoice_id' => $sourceInvoice->id,
                'allocated_from_payment_id' => $realPayment->id,
                'invoice_debt_rollover_id' => $rollover->id,
                'is_internal_allocation' => true,
                'amount' => $allocationAmount,
                'payment_date' => $realPayment->payment_date ?: now(),
                'payment_method' => $realPayment->payment_method,
                'transaction_reference' => null,
                'status' => Payment::STATUS_CONFIRMED,
                'proof_image' => null,
                'note' => 'Phân bổ nội bộ nợ cũ từ hóa đơn '.$targetInvoice->invoice_code,
                'collected_by' => $realPayment->collected_by,
            ]);

            $this->applyConfirmedPayment($sourceInvoice, $allocationAmount);

            $newSettledAmount = DecimalMoney::add([$rollover->settled_amount, $allocationAmount]);
            $rollover->forceFill([
                'settled_amount' => $newSettledAmount,
                'status' => DecimalMoney::compare($newSettledAmount, $rollover->amount) >= 0
                    ? InvoiceDebtRollover::STATUS_SETTLED
                    : InvoiceDebtRollover::STATUS_ACTIVE,
            ])->save();

            $this->settleRolloverIfSourceCleared($rollover->fresh(), $sourceInvoice->fresh());
            $this->allocateConfirmedPaymentToDebtRollovers($sourceInvoice->fresh(), $internalPayment);
            $remainingPaymentAmount = DecimalMoney::subtract($remainingPaymentAmount, $allocationAmount);
        }
    }

    // Tính tổng tiền nợ chuyển tiếp đi đang hoạt động
    private function activeRolloverOutAmount(Invoice $invoice, ?int $excludedTargetInvoiceId = null): string
    {
        $rollovers = $invoice->relationLoaded('debtRolloversOut')
            ? $invoice->debtRolloversOut
            : $invoice->debtRolloversOut()->with('targetInvoice:id,status')->get();

        if ($rollovers->contains(fn (InvoiceDebtRollover $rollover): bool => (int) $rollover->status === InvoiceDebtRollover::STATUS_SETTLED
            && (int) $rollover->target_invoice_id !== (int) $excludedTargetInvoiceId
            && (! $rollover->targetInvoice || (int) $rollover->targetInvoice->status !== Invoice::STATUS_CANCELLED))) {
            return '0.00';
        }

        $amounts = $rollovers
            ->filter(function (InvoiceDebtRollover $rollover) use ($excludedTargetInvoiceId): bool {
                if ((int) $rollover->status !== InvoiceDebtRollover::STATUS_ACTIVE) {
                    return false;
                }

                if ($excludedTargetInvoiceId !== null && (int) $rollover->target_invoice_id === $excludedTargetInvoiceId) {
                    return false;
                }

                return ! $rollover->targetInvoice || (int) $rollover->targetInvoice->status !== Invoice::STATUS_CANCELLED;
            })
            ->map(fn (InvoiceDebtRollover $rollover): string => DecimalMoney::maxZero(DecimalMoney::subtract($rollover->amount, $rollover->settled_amount)))
            ->all();

        return DecimalMoney::add($amounts);
    }

    // Tất toán khoản nợ chuyển tiếp nếu hóa đơn gốc đã thanh toán xong
    private function settleRolloverIfSourceCleared(InvoiceDebtRollover $rollover, Invoice $sourceInvoice): void
    {
        if (! DecimalMoney::isPositive($sourceInvoice->remaining_amount)) {
            $rollover->forceFill([
                'settled_amount' => $rollover->amount,
                'status' => InvoiceDebtRollover::STATUS_SETTLED,
            ])->save();
        }
    }

    // Áp dụng thanh toán đã xác nhận vào công nợ hóa đơn
    private function applyConfirmedPayment(Invoice $invoice, string $amount): void
    {
        $paidAmount = DecimalMoney::add([$invoice->paid_amount, $amount]);
        $remainingAmount = DecimalMoney::maxZero(DecimalMoney::subtract($invoice->total_amount, $paidAmount));

        if (DecimalMoney::compare($remainingAmount, '1.00') < 0) {
            $remainingAmount = '0.00';
        }

        $invoice->forceFill([
            'paid_amount' => $paidAmount,
            'remaining_amount' => $remainingAmount,
            'status' => DecimalMoney::compare($remainingAmount, '0') === 0
                ? Invoice::STATUS_PAID
                : Invoice::STATUS_PARTIALLY_PAID,
        ])->save();
    }

    // Tạo mã thanh toán cho khoản nợ chuyển tiếp
    private function makePaymentCode(): string
    {
        $prefix = 'PAY-'.now()->format('Y-m').'-';
        $next = Payment::query()
            ->where('payment_code', 'like', $prefix.'%')
            ->lockForUpdate()
            ->count() + 1;

        do {
            $code = $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            $next++;
        } while (Payment::query()->where('payment_code', $code)->exists());

        return $code;
    }
}
