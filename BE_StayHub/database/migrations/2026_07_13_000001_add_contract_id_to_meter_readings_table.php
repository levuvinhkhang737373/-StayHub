<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('meter_readings')) {
            return;
        }

        Schema::table('meter_readings', function (Blueprint $table): void {
            if (! Schema::hasColumn('meter_readings', 'contract_id')) {
                $table->foreignId('contract_id')
                    ->nullable()
                    ->after('meter_device_id')
                    ->constrained('contracts')
                    ->nullOnDelete();
            }
        });

        $this->backfillContractIds();

        $indexes = collect(Schema::getIndexes('meter_readings'))->pluck('name');

        Schema::table('meter_readings', function (Blueprint $table) use ($indexes): void {
            if (! $indexes->contains('meter_readings_meter_device_id_index')) {
                $table->index('meter_device_id', 'meter_readings_meter_device_id_index');
            }

            if ($indexes->contains('meter_readings_meter_device_id_billing_year_billing_month_unique')) {
                $table->dropUnique('meter_readings_meter_device_id_billing_year_billing_month_unique');
            }

            if (! $indexes->contains('meter_readings_contract_id_index')) {
                $table->index('contract_id', 'meter_readings_contract_id_index');
            }

            if (! $indexes->contains('meter_readings_device_contract_period_unique')) {
                $table->unique(['meter_device_id', 'contract_id', 'billing_year', 'billing_month'], 'meter_readings_device_contract_period_unique');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('meter_readings')) {
            return;
        }

        $indexes = collect(Schema::getIndexes('meter_readings'))->pluck('name');

        Schema::table('meter_readings', function (Blueprint $table) use ($indexes): void {
            if ($indexes->contains('meter_readings_device_contract_period_unique')) {
                $table->dropUnique('meter_readings_device_contract_period_unique');
            }

            if ($indexes->contains('meter_readings_contract_id_index')) {
                $table->dropIndex('meter_readings_contract_id_index');
            }

            if (! $indexes->contains('meter_readings_meter_device_id_billing_year_billing_month_unique')) {
                $table->unique(['meter_device_id', 'billing_year', 'billing_month'], 'meter_readings_meter_device_id_billing_year_billing_month_unique');
            }

            if ($indexes->contains('meter_readings_meter_device_id_index')) {
                $table->dropIndex('meter_readings_meter_device_id_index');
            }
        });

        Schema::table('meter_readings', function (Blueprint $table): void {
            if (Schema::hasColumn('meter_readings', 'contract_id')) {
                $table->dropConstrainedForeignId('contract_id');
            }
        });
    }

    private function backfillContractIds(): void
    {
        DB::table('meter_readings')
            ->join('meter_devices', 'meter_devices.id', '=', 'meter_readings.meter_device_id')
            ->whereNull('meter_readings.contract_id')
            ->orderBy('meter_readings.id')
            ->select([
                'meter_readings.id',
                'meter_readings.billing_month',
                'meter_readings.billing_year',
                'meter_devices.room_id',
            ])
            ->get()
            ->each(function (object $reading): void {
                $contractId = $this->contractIdForLegacyReading($reading);

                if ($contractId !== null) {
                    DB::table('meter_readings')
                        ->where('id', $reading->id)
                        ->update(['contract_id' => $contractId]);
                }
            });
    }

    private function contractIdForLegacyReading(object $reading): ?int
    {
        $invoiceContractId = DB::table('invoices')
            ->where('room_id', $reading->room_id)
            ->where('billing_month', $reading->billing_month)
            ->where('billing_year', $reading->billing_year)
            ->where('status', '!=', 6)
            ->orderByDesc('id')
            ->value('contract_id');

        if ($invoiceContractId !== null) {
            return (int) $invoiceContractId;
        }

        $periodStart = Carbon::create((int) $reading->billing_year, (int) $reading->billing_month, 1)->startOfDay()->toDateString();
        $periodEnd = Carbon::create((int) $reading->billing_year, (int) $reading->billing_month, 1)->endOfMonth()->toDateString();

        $candidates = DB::table('contracts')
            ->where('room_id', $reading->room_id)
            ->whereIn('status', [1, 2, 3])
            ->whereDate('start_date', '<=', $periodEnd)
            ->where(function ($query) use ($periodStart): void {
                $query->whereDate('actual_end_date', '>=', $periodStart)
                    ->orWhere(function ($endDateQuery) use ($periodStart): void {
                        $endDateQuery->whereNull('actual_end_date')
                            ->where(function ($query) use ($periodStart): void {
                                $query->whereNull('end_date')
                                    ->orWhereDate('end_date', '>=', $periodStart);
                            });
                    });
            })
            ->orderByDesc('status')
            ->pluck('id');

        return $candidates->count() === 1 ? (int) $candidates->first() : null;
    }
};
