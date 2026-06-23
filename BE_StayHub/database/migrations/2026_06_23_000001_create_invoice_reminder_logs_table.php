<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_reminder_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->foreignId('notification_id')->nullable()->constrained('notifications')->nullOnDelete();
            $table->date('reminder_date');
            $table->unsignedInteger('tenant_count')->default(0);
            $table->unsignedInteger('mail_queued_count')->default(0);
            $table->unsignedTinyInteger('status')->default(1);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['invoice_id', 'reminder_date'], 'invoice_reminder_logs_invoice_date_unique');
            $table->index(['reminder_date', 'status'], 'invoice_reminder_logs_date_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_reminder_logs');
    }
};
