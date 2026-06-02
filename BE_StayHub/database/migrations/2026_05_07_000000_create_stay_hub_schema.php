<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });

        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique();
            $table->string('full_name', 150);
            $table->string('email', 150)->unique();
            $table->string('phone', 30);
            $table->string('password');
            $table->unsignedTinyInteger('role')->default(1);
            $table->string('avatar_url')->nullable();
            $table->unsignedTinyInteger('status')->default(1);
            $table->unsignedTinyInteger('gender')->default(1);
            $table->string('address', 500)->nullable();
            $table->string('image_path_faceid', 500)->nullable();
            $table->timestamp('created_faceid_at')->nullable();
            $table->timestamp('updated_faceid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('full_name', 150);
            $table->unsignedTinyInteger('gender')->default(1);
            $table->date('date_of_birth');
            $table->string('phone', 30)->unique();
            $table->string('email', 150)->nullable()->unique();
            $table->string('username')->unique();
            $table->string('password');
            $table->string('permanent_address', 500)->nullable();
            $table->string('current_address', 500)->nullable();
            $table->string('avatar_url', 500)->nullable();
            $table->unsignedTinyInteger('status')->default(1);
            $table->unsignedTinyInteger('identity_type')->default(1);
            $table->string('identity_number', 30)->unique();
            $table->string('front_image_url', 500)->nullable();
            $table->string('back_image_url', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->string('path')->nullable();
            $table->string('slug')->nullable()->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('buildings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained('regions')->restrictOnDelete();
            $table->foreignId('manager_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('name', 150);
            $table->string('slug')->nullable()->unique();
            $table->string('address', 500)->nullable();
            $table->unsignedInteger('total_floors')->nullable();
            $table->unsignedTinyInteger('gender_policy')->default(1);
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('status')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('building_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained('buildings')->cascadeOnDelete();
            $table->string('image_path', 500);
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedTinyInteger('status')->default(1);
            $table->foreignId('uploaded_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->index(['building_id', 'status', 'sort_order']);
            $table->index(['building_id', 'is_primary']);
        });

        Schema::create('room_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->string('slug')->nullable()->unique();
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('status')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained('buildings')->restrictOnDelete();
            $table->foreignId('room_type_id')->constrained('room_types')->restrictOnDelete();
            $table->string('room_number', 50);
            $table->string('slug')->nullable();
            $table->integer('floor')->nullable();
            $table->decimal('area_m2', 8, 2)->nullable();
            $table->decimal('base_price', 15, 2);
            $table->unsignedInteger('max_occupants');
            $table->unsignedInteger('current_occupants')->default(0);
            $table->unsignedTinyInteger('status')->default(1);
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->unique(['building_id', 'slug']);
        });

        Schema::create('room_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->string('image_path', 500);
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedTinyInteger('status')->default(1);
            $table->foreignId('uploaded_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->index(['room_id', 'status', 'sort_order']);
            $table->index(['room_id', 'is_primary']);
        });

        Schema::create('asset_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('slug')->nullable();
            $table->foreignId('building_id')->nullable()->constrained('buildings')->nullOnDelete();
            $table->unsignedTinyInteger('default_unit_name')->nullable()->default(1);
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('status')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->index(['building_id', 'status']);
        });

        Schema::create('room_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->foreignId('asset_template_id')->constrained('asset_templates')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('price', 15, 2)->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();
            $table->unique(['room_id', 'asset_template_id']);
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('service_code', 50)->unique();
            $table->string('name');
            $table->string('slug')->nullable()->unique();
            $table->string('service_type');
            $table->unsignedTinyInteger('charge_method')->default(1);
            $table->string('unit_name', 50)->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('service_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignId('building_id')->constrained('buildings')->cascadeOnDelete();
            $table->decimal('price', 15, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->unsignedTinyInteger('status')->default(1);
            $table->timestamps();
            $table->unique(['service_id', 'building_id', 'effective_from']);
        });

        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('contract_code', 50)->unique();
            $table->foreignId('room_id')->constrained('rooms')->restrictOnDelete();
            $table->foreignId('representative_tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->unsignedTinyInteger('billing_cycle_day');
            $table->decimal('room_price', 15, 2);
            $table->decimal('deposit_amount', 15, 2);
            $table->unsignedTinyInteger('status')->default(1);
            $table->json('contract_files')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('contract_tenants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->date('join_date');
            $table->date('leave_date')->nullable();
            $table->date('billing_start_date')->nullable();
            $table->date('billing_end_date')->nullable();
            $table->boolean('is_representative')->default(false);
            $table->boolean('is_staying')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->unique(['contract_id', 'tenant_id']);
        });

        Schema::create('room_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->foreignId('from_room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->foreignId('to_room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->unsignedTinyInteger('movement_type')->default(1);
            $table->dateTime('movement_date');
            $table->decimal('old_room_final_amount', 15, 2);
            $table->decimal('transfer_fee', 15, 2);
            $table->decimal('deposit_transfer_amount', 15, 2);
            $table->decimal('deposit_refund_amount', 15, 2);
            $table->decimal('deduction_amount', 15, 2);
            $table->decimal('final_electric_reading', 12, 2)->nullable();
            $table->decimal('final_water_reading', 12, 2)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedTinyInteger('vehicle_type')->default(1);
            $table->string('license_plate', 30)->unique();
            $table->string('brand', 100)->nullable();
            $table->string('color', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('contract_vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->restrictOnDelete();
            $table->date('started_at');
            $table->date('ended_at')->nullable();
            $table->date('billing_start_date')->nullable();
            $table->date('billing_end_date')->nullable();
            $table->decimal('monthly_fee', 15, 2);
            $table->unsignedTinyInteger('charge_policy')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['contract_id', 'vehicle_id']);
        });

        Schema::create('contract_deposit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $table->unsignedTinyInteger('transaction_type')->default(1);
            $table->decimal('amount', 15, 2);
            $table->date('transaction_date');
            $table->unsignedTinyInteger('payment_method')->nullable()->default(1);
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('meter_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms')->restrictOnDelete();
            $table->foreignId('service_id')->constrained('services')->restrictOnDelete();
            $table->string('meter_code', 100)->nullable()->unique();
            $table->unsignedTinyInteger('meter_type')->default(1);
            $table->decimal('initial_reading', 12, 2);
            $table->date('installed_at')->nullable();
            $table->foreignId('replaced_by_meter_id')->nullable()->constrained('meter_devices')->nullOnDelete();
            $table->decimal('final_reading', 12, 2)->nullable();
            $table->unsignedTinyInteger('status')->default(1);
            $table->string('image_path', 500)->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('meter_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meter_device_id')->constrained('meter_devices')->cascadeOnDelete();
            $table->unsignedTinyInteger('billing_month');
            $table->unsignedSmallInteger('billing_year');
            $table->decimal('previous_reading', 12, 2);
            $table->decimal('current_reading', 12, 2);
            $table->decimal('consumption', 12, 2);
            $table->date('reading_date');
            $table->unsignedTinyInteger('status')->default(1);
            $table->string('image_path', 500)->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->unique(['meter_device_id', 'billing_year', 'billing_month']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_code')->unique();
            $table->foreignId('contract_id')->constrained('contracts')->restrictOnDelete();
            $table->foreignId('room_id')->constrained('rooms')->restrictOnDelete();
            $table->unsignedTinyInteger('billing_month');
            $table->unsignedSmallInteger('billing_year');
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('previous_debt_amount', 15, 2);
            $table->decimal('total_amount', 15, 2);
            $table->decimal('paid_amount', 15, 2);
            $table->decimal('remaining_amount', 15, 2);
            $table->date('due_date')->nullable();
            $table->unsignedTinyInteger('status')->default(1);
            $table->dateTime('issued_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->index(['room_id', 'billing_year', 'billing_month']);
            $table->unique(['contract_id', 'billing_year', 'billing_month']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->foreignId('meter_reading_id')->nullable()->constrained('meter_readings')->nullOnDelete();
            $table->unsignedTinyInteger('item_type')->default(1);
            $table->string('description');
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('amount', 15, 2);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_code', 50)->unique();
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->dateTime('payment_date');
            $table->unsignedTinyInteger('payment_method')->default(1);
            $table->string('transaction_reference', 150)->nullable();
            $table->unsignedTinyInteger('status')->default(1);
            $table->string('proof_image', 500)->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('collected_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->index(['invoice_id', 'status']);
        });

        Schema::create('maintenance_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_code', 50)->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('room_id')->constrained('rooms')->restrictOnDelete();
            $table->string('title');
            $table->text('description');
            $table->unsignedTinyInteger('status')->default(1);
            $table->json('images')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('admins')->nullOnDelete();
            $table->dateTime('received_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('maintenance_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_request_id')->constrained('maintenance_requests')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->json('images')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->unique(['maintenance_request_id', 'tenant_id']);
        });

        Schema::create('maintenance_request_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_request_id')->constrained('maintenance_requests')->cascadeOnDelete();
            $table->unsignedTinyInteger('old_status')->nullable();
            $table->unsignedTinyInteger('new_status');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->unsignedTinyInteger('notification_type')->default(1);
            $table->unsignedTinyInteger('target_type')->default(1);
            $table->foreignId('building_id')->nullable()->constrained('buildings')->nullOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->dateTime('published_at')->nullable();
            $table->unsignedTinyInteger('status')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('notification_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained('notifications')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->dateTime('read_at');
            $table->unique(['notification_id', 'tenant_id']);
        });

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_code', 50)->unique();
            $table->foreignId('building_id')->nullable()->constrained('buildings')->nullOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->foreignId('expense_category_id')->nullable()->constrained('expense_categories')->nullOnDelete();
            $table->string('title');
            $table->decimal('amount', 15, 2);
            $table->date('expense_date');
            $table->json('receipt_images')->nullable();
            $table->unsignedTinyInteger('payment_method')->nullable()->default(1);
            $table->text('note')->nullable();
            $table->unsignedTinyInteger('status')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->index(['building_id', 'expense_date']);
            $table->index(['room_id', 'expense_date']);
            $table->index(['expense_category_id', 'status']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->nullable()->constrained('buildings')->cascadeOnDelete();
            $table->string('setting_label', 150);
            $table->string('setting_name')->nullable();
            $table->string('setting_value', 500)->nullable();
            $table->string('description', 500)->nullable();
            $table->boolean('is_public')->default(true);
            $table->foreignId('created_by')->constrained('admins')->restrictOnDelete();
            $table->timestamps();
            $table->index(['building_id', 'is_public']);
        });

        Schema::create('admin_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->string('action', 100);
            $table->string('entity_type', 100);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('old_data')->nullable();
            $table->json('new_data')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_logs');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('notification_reads');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('maintenance_request_logs');
        Schema::dropIfExists('maintenance_feedbacks');
        Schema::dropIfExists('maintenance_requests');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('meter_readings');
        Schema::dropIfExists('meter_devices');
        Schema::dropIfExists('contract_deposit_transactions');
        Schema::dropIfExists('contract_vehicles');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('room_movements');
        Schema::dropIfExists('contract_tenants');
        Schema::dropIfExists('contracts');
        Schema::dropIfExists('service_prices');
        Schema::dropIfExists('services');
        Schema::dropIfExists('room_assets');
        Schema::dropIfExists('asset_templates');
        Schema::dropIfExists('room_images');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('room_types');
        Schema::dropIfExists('building_images');
        Schema::dropIfExists('buildings');
        Schema::dropIfExists('regions');
        Schema::dropIfExists('tenants');
        Schema::dropIfExists('admins');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
