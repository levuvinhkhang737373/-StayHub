<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AdminScope;
use App\Helpers\ApiResponse;
use App\Helpers\DecimalMoney;
use App\Helpers\ImageHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PaymentHistory\IndexRequest;
use App\Http\Resources\Admin\PaymentHistoryResource;
use App\Models\Admin;
use App\Models\ContractDepositTransaction;
use App\Models\Payment;
use App\Models\RoomMovement;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PaymentHistoryController extends Controller
{
    private const SOURCE_INVOICE_PAYMENT = 'invoice_payment';
    private const SOURCE_DEPOSIT_TRANSACTION = 'deposit_transaction';
    private const SOURCE_ROOM_TRANSFER = 'room_transfer';

    private const STATUS_PENDING = 'pending';
    private const STATUS_CONFIRMED = 'confirmed';
    private const STATUS_CANCELLED = 'cancelled';
    private const STATUS_PARTIAL = 'partial';
    private const STATUS_PAID = 'paid';

    private const DIRECTION_IN = 'in';
    private const DIRECTION_OUT = 'out';
    private const DIRECTION_ADJUSTMENT = 'adjustment';

    public function index(IndexRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $admin = $request->user('admin');

            if (! $admin || (! AdminScope::isSuperAdmin($admin) && ! AdminScope::isBuildingManager($admin))) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xem lịch sử thanh toán', 403, null, 403);
            }

            if (isset($validated['building_id']) && ! AdminScope::ensureBuildingAccess($admin, (int) $validated['building_id'])) {
                return ApiResponse::responseJson(false, 'Bạn không có quyền xem lịch sử thanh toán của tòa nhà này', 403, null, 403);
            }

            $records = $this->collectRecords($validated, $admin)
                ->filter(fn (array $record): bool => $this->matchesCommonFilters($record, $validated))
                ->sortByDesc(fn (array $record): string => ($record['event_date'] ?? '').'|'.str_pad((string) ($record['source_id'] ?? 0), 12, '0', STR_PAD_LEFT))
                ->values();

            $summary = $this->summary($records);
            $paginator = $this->paginate($records, (int) ($validated['per_page'] ?? 10), (int) ($validated['page'] ?? 1), $request->url());

            return ApiResponse::responseJson(true, 'Lịch sử thanh toán', 200, [
                'data' => PaymentHistoryResource::collection($paginator->items())->resolve(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
                'summary' => $summary,
            ], 200);
        } catch (\Exception $e) {
            return ApiResponse::responseJson(false, 'Server Error: '.$e->getMessage(), 500, null, 500);
        }
    }

    private function collectRecords(array $validated, Admin $admin): Collection
    {
        $sourceType = $validated['source_type'] ?? null;
        $records = collect();

        if (! $sourceType || $sourceType === self::SOURCE_INVOICE_PAYMENT) {
            $records = $records->merge($this->invoicePaymentRecords($validated, $admin));
        }

        if (! $sourceType || $sourceType === self::SOURCE_DEPOSIT_TRANSACTION) {
            $records = $records->merge($this->depositTransactionRecords($validated, $admin));
        }

        if (! $sourceType || $sourceType === self::SOURCE_ROOM_TRANSFER) {
            $records = $records->merge($this->roomTransferRecords($validated, $admin));
        }

        return $records;
    }

    private function invoicePaymentRecords(array $validated, Admin $admin): Collection
    {
        $query = Payment::query()
            ->realMoney()
            ->with([
                'collector:id,full_name,username,email',
                'invoice:id,invoice_code,contract_id,room_id,billing_month,billing_year,status,total_amount,paid_amount,remaining_amount',
                'invoice.room:id,building_id,room_number,floor',
                'invoice.room.building:id,name,slug,manager_admin_id',
                'invoice.contract:id,contract_code,room_id',
                'invoice.contract.contractTenants:id,contract_id,tenant_id,is_staying',
                'invoice.contract.contractTenants.tenant:id,full_name,phone,email',
            ]);

        $this->applyInvoicePaymentFilters($query, $validated, $admin);

        return $query->get()
            ->filter(fn (Payment $payment): bool => $payment->invoice !== null && $payment->invoice->room !== null)
            ->map(fn (Payment $payment): array => $this->invoicePaymentRecord($payment));
    }

    private function depositTransactionRecords(array $validated, Admin $admin): Collection
    {
        $query = ContractDepositTransaction::query()
            ->whereIn('payment_method', [
                ContractDepositTransaction::PAYMENT_METHOD_CASH,
                ContractDepositTransaction::PAYMENT_METHOD_BANK_TRANSFER,
            ])
            ->whereIn('transaction_type', [
                ContractDepositTransaction::TRANSACTION_TYPE_COLLECT,
                ContractDepositTransaction::TRANSACTION_TYPE_REFUND,
            ])
            ->with([
                'creator:id,full_name,username,email',
                'contract:id,contract_code,room_id,deposit_amount,status,payment_status',
                'contract.room:id,building_id,room_number,floor',
                'contract.room.building:id,name,slug,manager_admin_id',
                'contract.contractTenants:id,contract_id,tenant_id,is_staying',
                'contract.contractTenants.tenant:id,full_name,phone,email',
            ]);

        $this->applyDepositTransactionFilters($query, $validated, $admin);

        return $query->get()
            ->filter(fn (ContractDepositTransaction $transaction): bool => $transaction->contract !== null && $transaction->contract->room !== null)
            ->map(fn (ContractDepositTransaction $transaction): array => $this->depositTransactionRecord($transaction));
    }

    private function roomTransferRecords(array $validated, Admin $admin): Collection
    {
        $query = RoomMovement::query()
            ->whereNotNull('settlement_payment_references')
            ->with([
                'tenant:id,full_name,phone,email',
                'toRoom:id,building_id,room_number,floor',
                'toRoom.building:id,name,slug,manager_admin_id',
                'fromRoom:id,building_id,room_number,floor',
                'fromRoom.building:id,name,slug,manager_admin_id',
                'destinationContract:id,contract_code,room_id,status,payment_status',
                'sourceContract:id,contract_code,room_id,status,payment_status',
            ]);

        $this->applyRoomTransferFilters($query, $validated, $admin);

        return $query->get()
            ->flatMap(fn (RoomMovement $movement): Collection => $this->roomTransferMovementRecords($movement));
    }

    private function applyInvoicePaymentFilters(Builder $query, array $validated, Admin $admin): void
    {
        if (AdminScope::isBuildingManager($admin)) {
            $query->whereHas('invoice.room.building', fn (Builder $buildingQuery): Builder => $buildingQuery->where('manager_admin_id', $admin->id));
        }

        $query
            ->when(isset($validated['building_id']), fn (Builder $q): Builder => $q->whereHas('invoice.room', fn (Builder $roomQuery): Builder => $roomQuery->where('building_id', (int) $validated['building_id'])))
            ->when(isset($validated['room_id']), fn (Builder $q): Builder => $q->whereHas('invoice', fn (Builder $invoiceQuery): Builder => $invoiceQuery->where('room_id', (int) $validated['room_id'])))
            ->when(isset($validated['contract_id']), fn (Builder $q): Builder => $q->whereHas('invoice', fn (Builder $invoiceQuery): Builder => $invoiceQuery->where('contract_id', (int) $validated['contract_id'])))
            ->when(isset($validated['invoice_id']), fn (Builder $q): Builder => $q->where('invoice_id', (int) $validated['invoice_id']))
            ->when(isset($validated['payment_method']), fn (Builder $q): Builder => $q->where('payment_method', (int) $validated['payment_method']))
            ->when(isset($validated['date_from']), fn (Builder $q): Builder => $q->whereDate('payment_date', '>=', $validated['date_from']))
            ->when(isset($validated['date_to']), fn (Builder $q): Builder => $q->whereDate('payment_date', '<=', $validated['date_to']));
    }

    private function applyDepositTransactionFilters(Builder $query, array $validated, Admin $admin): void
    {
        if (AdminScope::isBuildingManager($admin)) {
            $query->whereHas('contract.room.building', fn (Builder $buildingQuery): Builder => $buildingQuery->where('manager_admin_id', $admin->id));
        }

        $query
            ->when(isset($validated['building_id']), fn (Builder $q): Builder => $q->whereHas('contract.room', fn (Builder $roomQuery): Builder => $roomQuery->where('building_id', (int) $validated['building_id'])))
            ->when(isset($validated['room_id']), fn (Builder $q): Builder => $q->whereHas('contract', fn (Builder $contractQuery): Builder => $contractQuery->where('room_id', (int) $validated['room_id'])))
            ->when(isset($validated['contract_id']), fn (Builder $q): Builder => $q->where('contract_id', (int) $validated['contract_id']))
            ->when(isset($validated['payment_method']), fn (Builder $q): Builder => $q->where('payment_method', (int) $validated['payment_method']))
            ->when(isset($validated['deposit_transaction_type']), fn (Builder $q): Builder => $q->where('transaction_type', (int) $validated['deposit_transaction_type']))
            ->when(isset($validated['date_from']), fn (Builder $q): Builder => $q->whereDate('transaction_date', '>=', $validated['date_from']))
            ->when(isset($validated['date_to']), fn (Builder $q): Builder => $q->whereDate('transaction_date', '<=', $validated['date_to']));
    }

    private function applyRoomTransferFilters(Builder $query, array $validated, Admin $admin): void
    {
        if (AdminScope::isBuildingManager($admin)) {
            $query->whereHas('toRoom.building', fn (Builder $buildingQuery): Builder => $buildingQuery->where('manager_admin_id', $admin->id));
        }

        $query
            ->when(isset($validated['building_id']), fn (Builder $q): Builder => $q->whereHas('toRoom', fn (Builder $roomQuery): Builder => $roomQuery->where('building_id', (int) $validated['building_id'])))
            ->when(isset($validated['room_id']), fn (Builder $q): Builder => $q->where('to_room_id', (int) $validated['room_id']))
            ->when(isset($validated['contract_id']), fn (Builder $q): Builder => $q->where('destination_contract_id', (int) $validated['contract_id']));
    }

    private function invoicePaymentRecord(Payment $payment): array
    {
        $invoice = $payment->invoice;
        $room = $invoice?->room;
        $building = $room?->building;
        $contract = $invoice?->contract;
        $eventDate = optional($payment->payment_date)->toDateTimeString() ?? optional($payment->created_at)->toDateTimeString();

        return [
            'uid' => self::SOURCE_INVOICE_PAYMENT.'-'.$payment->id,
            'source_type' => self::SOURCE_INVOICE_PAYMENT,
            'source_label' => 'Thanh toán hóa đơn',
            'source_id' => $payment->id,
            'event_date' => $eventDate,
            'amount' => DecimalMoney::normalize($payment->amount),
            'signed_amount' => DecimalMoney::normalize($payment->amount),
            'amount_direction' => self::DIRECTION_IN,
            'payment_method' => $payment->payment_method,
            'payment_method_label' => Payment::PAYMENT_METHOD_LABELS[$payment->payment_method] ?? null,
            'status_group' => $this->paymentStatusGroup((int) $payment->status),
            'status_label' => Payment::STATUS_LABELS[$payment->status] ?? null,
            'transaction_reference' => $payment->transaction_reference,
            'code' => $payment->payment_code,
            'building' => $this->buildingPayload($building),
            'room' => $this->roomPayload($room),
            'contract' => $this->contractPayload($contract),
            'invoice' => $this->invoicePayload($invoice),
            'tenants' => $this->contractTenantsPayload($contract),
            'actor_name' => $payment->collector?->full_name ?? $payment->collector?->username,
            'proof_image_url' => $payment->proof_image ? ImageHelper::load($payment->proof_image) : null,
            'note' => $payment->note,
            'can_confirm' => (int) $payment->status === Payment::STATUS_PENDING_CONFIRMATION,
            'metadata' => [
                'payment_id' => $payment->id,
                'invoice_id' => $payment->invoice_id,
                'invoice_status' => $invoice?->status,
                'created_at' => optional($payment->created_at)->toDateTimeString(),
                'updated_at' => optional($payment->updated_at)->toDateTimeString(),
            ],
        ];
    }

    private function depositTransactionRecord(ContractDepositTransaction $transaction): array
    {
        $contract = $transaction->contract;
        $room = $contract?->room;
        $building = $room?->building;
        $direction = $this->depositDirection((int) $transaction->transaction_type);
        $amount = DecimalMoney::normalize($transaction->amount);
        $eventDate = optional($transaction->transaction_date)->toDateString()
            ? optional($transaction->transaction_date)->toDateString().' 00:00:00'
            : optional($transaction->created_at)->toDateTimeString();

        return [
            'uid' => self::SOURCE_DEPOSIT_TRANSACTION.'-'.$transaction->id,
            'source_type' => self::SOURCE_DEPOSIT_TRANSACTION,
            'source_label' => ContractDepositTransaction::TRANSACTION_TYPE_LABELS[$transaction->transaction_type] ?? 'Giao dịch cọc',
            'source_id' => $transaction->id,
            'event_date' => $eventDate,
            'amount' => $amount,
            'signed_amount' => $direction === self::DIRECTION_OUT ? DecimalMoney::normalize('-'.$amount) : $amount,
            'amount_direction' => $direction,
            'payment_method' => $transaction->payment_method,
            'payment_method_label' => ContractDepositTransaction::PAYMENT_METHOD_LABELS[$transaction->payment_method] ?? null,
            'status_group' => self::STATUS_CONFIRMED,
            'status_label' => 'Đã ghi nhận',
            'transaction_reference' => $transaction->transaction_reference,
            'code' => $contract?->contract_code,
            'building' => $this->buildingPayload($building),
            'room' => $this->roomPayload($room),
            'contract' => $this->contractPayload($contract),
            'invoice' => null,
            'tenants' => $this->contractTenantsPayload($contract),
            'actor_name' => $transaction->creator?->full_name ?? $transaction->creator?->username,
            'proof_image_url' => null,
            'note' => $transaction->note,
            'can_confirm' => false,
            'metadata' => [
                'deposit_transaction_id' => $transaction->id,
                'transaction_type' => $transaction->transaction_type,
                'transaction_type_label' => ContractDepositTransaction::TRANSACTION_TYPE_LABELS[$transaction->transaction_type] ?? null,
                'created_at' => optional($transaction->created_at)->toDateTimeString(),
            ],
        ];
    }

    private function roomTransferMovementRecords(RoomMovement $movement): Collection
    {
        $references = collect($movement->settlement_payment_references ?? [])
            ->filter(fn ($reference): bool => is_array($reference) && DecimalMoney::isPositive($reference['amount'] ?? '0'));

        return $references->map(function (array $reference, int $index) use ($movement): array {
            $room = $movement->toRoom ?: $movement->fromRoom;
            $building = $room?->building;
            $contract = $movement->destinationContract ?: $movement->sourceContract;
            $amount = DecimalMoney::normalize($reference['amount'] ?? '0');
            $eventDate = $reference['paid_at'] ?? optional($movement->executed_at)->toDateTimeString() ?? optional($movement->created_at)->toDateTimeString();
            $paymentMethod = (int) ($reference['payment_method'] ?? Payment::PAYMENT_METHOD_BANK_TRANSFER);
            if (! array_key_exists($paymentMethod, Payment::PAYMENT_METHOD_LABELS)) {
                $paymentMethod = Payment::PAYMENT_METHOD_BANK_TRANSFER;
            }

            return [
                'uid' => self::SOURCE_ROOM_TRANSFER.'-'.$movement->id.'-'.$index.'-'.sha1((string) ($reference['reference'] ?? $eventDate)),
                'source_type' => self::SOURCE_ROOM_TRANSFER,
                'source_label' => 'Thanh toán chuyển phòng',
                'source_id' => $movement->id,
                'event_date' => $eventDate,
                'amount' => $amount,
                'signed_amount' => $amount,
                'amount_direction' => self::DIRECTION_IN,
                'payment_method' => $paymentMethod,
                'payment_method_label' => Payment::PAYMENT_METHOD_LABELS[$paymentMethod],
                'status_group' => $this->roomTransferStatusGroup((int) $movement->settlement_payment_status),
                'status_label' => RoomMovement::SETTLEMENT_PAYMENT_STATUS_LABELS[$movement->settlement_payment_status] ?? null,
                'transaction_reference' => $reference['reference'] ?? null,
                'code' => $movement->transfer_code,
                'building' => $this->buildingPayload($building),
                'room' => $this->roomPayload($room),
                'contract' => $this->contractPayload($contract),
                'invoice' => null,
                'tenants' => $movement->tenant ? [[
                    'id' => $movement->tenant->id,
                    'full_name' => $movement->tenant->full_name,
                    'phone' => $movement->tenant->phone,
                    'email' => $movement->tenant->email,
                    'is_staying' => null,
                ]] : [],
                'actor_name' => $reference['collector_name'] ?? 'SePay Webhook',
                'proof_image_url' => null,
                'note' => $reference['note'] ?? $movement->note,
                'can_confirm' => false,
                'metadata' => [
                    'room_movement_id' => $movement->id,
                    'transfer_code' => $movement->transfer_code,
                    'deposit_amount' => DecimalMoney::normalize($reference['deposit_amount'] ?? '0'),
                    'extra_amount' => DecimalMoney::normalize($reference['extra_amount'] ?? '0'),
                    'settlement_due_amount' => DecimalMoney::normalize($movement->settlement_due_amount ?? '0'),
                    'settlement_paid_amount' => DecimalMoney::normalize($movement->settlement_paid_amount ?? '0'),
                ],
            ];
        })->values();
    }

    private function matchesCommonFilters(array $record, array $validated): bool
    {
        if (($validated['amount_direction'] ?? null) && $record['amount_direction'] !== $validated['amount_direction']) {
            return false;
        }

        if (($validated['status_group'] ?? null) && $record['status_group'] !== $validated['status_group']) {
            return false;
        }

        if (isset($validated['payment_method']) && (int) ($record['payment_method'] ?? 0) !== (int) $validated['payment_method']) {
            return false;
        }

        if (isset($validated['invoice_id']) && (int) ($record['invoice']['id'] ?? 0) !== (int) $validated['invoice_id']) {
            return false;
        }

        if (isset($validated['date_from']) || isset($validated['date_to'])) {
            $eventDate = $this->eventCarbon($record['event_date'] ?? null);
            if (! $eventDate) {
                return false;
            }

            if (isset($validated['date_from']) && $eventDate->lt(Carbon::parse($validated['date_from'])->startOfDay())) {
                return false;
            }

            if (isset($validated['date_to']) && $eventDate->gt(Carbon::parse($validated['date_to'])->endOfDay())) {
                return false;
            }
        }

        $keyword = trim((string) ($validated['keyword'] ?? ''));
        if ($keyword !== '' && ! str_contains($this->searchText($record), mb_strtolower($keyword))) {
            return false;
        }

        return true;
    }

    private function summary(Collection $records): array
    {
        $bySource = [
            self::SOURCE_INVOICE_PAYMENT => 0,
            self::SOURCE_DEPOSIT_TRANSACTION => 0,
            self::SOURCE_ROOM_TRANSFER => 0,
        ];

        $totalIn = 0;
        $totalOut = 0;
        $pendingCount = 0;

        foreach ($records as $record) {
            $bySource[$record['source_type']] = ($bySource[$record['source_type']] ?? 0) + 1;

            $amount = DecimalMoney::toScaledInteger($record['amount'] ?? '0');
            if ($record['amount_direction'] === self::DIRECTION_OUT) {
                $totalOut += $amount;
            } else {
                $totalIn += $amount;
            }

            if ($record['status_group'] === self::STATUS_PENDING) {
                $pendingCount++;
            }
        }

        return [
            'total_transactions' => $records->count(),
            'total_in_amount' => $this->scaledToDecimal($totalIn),
            'total_out_amount' => $this->scaledToDecimal($totalOut),
            'pending_count' => $pendingCount,
            'by_source' => $bySource,
        ];
    }

    private function paginate(Collection $records, int $perPage, int $page, string $path): LengthAwarePaginator
    {
        $perPage = max(1, min($perPage, 100));
        $page = max(1, $page);
        $items = $records->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator($items, $records->count(), $perPage, $page, [
            'path' => $path,
        ]);
    }

    private function paymentStatusGroup(int $status): string
    {
        return match ($status) {
            Payment::STATUS_PENDING_CONFIRMATION => self::STATUS_PENDING,
            Payment::STATUS_CANCELLED => self::STATUS_CANCELLED,
            default => self::STATUS_CONFIRMED,
        };
    }

    private function roomTransferStatusGroup(int $status): string
    {
        return match ($status) {
            RoomMovement::SETTLEMENT_PAYMENT_STATUS_PARTIAL => self::STATUS_PARTIAL,
            RoomMovement::SETTLEMENT_PAYMENT_STATUS_PAID => self::STATUS_PAID,
            default => self::STATUS_CONFIRMED,
        };
    }

    private function depositDirection(int $transactionType): string
    {
        return $transactionType === ContractDepositTransaction::TRANSACTION_TYPE_REFUND
            ? self::DIRECTION_OUT
            : self::DIRECTION_IN;
    }

    private function buildingPayload($building): ?array
    {
        if (! $building) {
            return null;
        }

        return [
            'id' => $building->id,
            'name' => $building->name,
            'slug' => $building->slug,
        ];
    }

    private function roomPayload($room): ?array
    {
        if (! $room) {
            return null;
        }

        return [
            'id' => $room->id,
            'building_id' => $room->building_id,
            'room_number' => $room->room_number,
            'floor' => $room->floor,
        ];
    }

    private function contractPayload($contract): ?array
    {
        if (! $contract) {
            return null;
        }

        return [
            'id' => $contract->id,
            'contract_code' => $contract->contract_code,
            'room_id' => $contract->room_id,
            'status' => $contract->status,
            'payment_status' => $contract->payment_status,
        ];
    }

    private function invoicePayload($invoice): ?array
    {
        if (! $invoice) {
            return null;
        }

        return [
            'id' => $invoice->id,
            'invoice_code' => $invoice->invoice_code,
            'contract_id' => $invoice->contract_id,
            'room_id' => $invoice->room_id,
            'billing_month' => $invoice->billing_month,
            'billing_year' => $invoice->billing_year,
            'status' => $invoice->status,
            'total_amount' => $invoice->total_amount === null ? null : (string) $invoice->total_amount,
            'paid_amount' => $invoice->paid_amount === null ? null : (string) $invoice->paid_amount,
            'remaining_amount' => $invoice->remaining_amount === null ? null : (string) $invoice->remaining_amount,
        ];
    }

    private function contractTenantsPayload($contract): array
    {
        if (! $contract || ! $contract->relationLoaded('contractTenants')) {
            return [];
        }

        return $contract->contractTenants
            ->filter(fn ($contractTenant): bool => $contractTenant->tenant !== null)
            ->map(fn ($contractTenant): array => [
                'id' => $contractTenant->tenant->id,
                'full_name' => $contractTenant->tenant->full_name,
                'phone' => $contractTenant->tenant->phone,
                'email' => $contractTenant->tenant->email,
                'is_staying' => (bool) $contractTenant->is_staying,
            ])
            ->values()
            ->all();
    }

    private function searchText(array $record): string
    {
        $parts = [
            $record['source_label'] ?? null,
            $record['transaction_reference'] ?? null,
            $record['code'] ?? null,
            $record['building']['name'] ?? null,
            $record['room']['room_number'] ?? null,
            $record['contract']['contract_code'] ?? null,
            $record['invoice']['invoice_code'] ?? null,
            $record['actor_name'] ?? null,
            $record['note'] ?? null,
        ];

        foreach (($record['tenants'] ?? []) as $tenant) {
            $parts[] = $tenant['full_name'] ?? null;
            $parts[] = $tenant['phone'] ?? null;
            $parts[] = $tenant['email'] ?? null;
        }

        return mb_strtolower(collect($parts)->filter()->implode(' '));
    }

    private function eventCarbon(?string $eventDate): ?Carbon
    {
        if (! $eventDate) {
            return null;
        }

        try {
            return Carbon::parse($eventDate);
        } catch (\Exception) {
            return null;
        }
    }

    private function scaledToDecimal(int $scaled): string
    {
        $sign = $scaled < 0 ? '-' : '';
        $scaled = abs($scaled);
        $integer = intdiv($scaled, 100);
        $fraction = str_pad((string) ($scaled % 100), 2, '0', STR_PAD_LEFT);

        return $sign.$integer.'.'.$fraction;
    }
}
