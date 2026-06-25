<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->unsignedInteger('revision')->default(1)->after('issued_at');
            $table->dateTime('reissued_at')->nullable()->after('revision');
            $table->string('reissue_reason', 500)->nullable()->after('reissued_at');
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('admins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn(['revision', 'reissued_at', 'reissue_reason']);
        });
    }
};
