<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_cameras', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('building_id')->constrained('buildings')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('location', 160)->nullable();
            $table->unsignedTinyInteger('source_type')->default(1);
            $table->text('stream_url')->nullable();
            $table->string('username', 120)->nullable();
            $table->string('password', 255)->nullable();
            $table->boolean('is_ai_enabled')->default(false);
            $table->unsignedSmallInteger('frame_interval_seconds')->default(2);
            $table->unsignedTinyInteger('frames_per_batch')->default(3);
            $table->unsignedSmallInteger('alert_cooldown_seconds')->default(60);
            $table->unsignedTinyInteger('status')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['building_id', 'status']);
            $table->index(['is_ai_enabled', 'status']);
        });

        Schema::create('fire_safety_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('security_camera_id')->nullable()->constrained('security_cameras')->nullOnDelete();
            $table->foreignId('building_id')->constrained('buildings')->cascadeOnDelete();
            $table->string('source_label', 160)->nullable();
            $table->unsignedTinyInteger('risk_level')->default(1);
            $table->boolean('detected_fire')->default(false);
            $table->boolean('detected_smoke')->default(false);
            $table->boolean('detected_smoking')->default(false);
            $table->decimal('confidence', 5, 4)->default(0);
            $table->string('snapshot_path')->nullable();
            $table->text('ai_summary')->nullable();
            $table->json('raw_ai_payload')->nullable();
            $table->unsignedTinyInteger('status')->default(1);
            $table->foreignId('acknowledged_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['building_id', 'status', 'created_at']);
            $table->index(['security_camera_id', 'created_at']);
            $table->index(['risk_level', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fire_safety_alerts');
        Schema::dropIfExists('security_cameras');
    }
};
