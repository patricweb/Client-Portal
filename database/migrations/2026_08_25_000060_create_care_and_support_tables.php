<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('type');
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('monthly_price', 12, 2);
            $table->char('currency', 3)->default('USD');
            $table->string('billing_frequency')->default('monthly');
            $table->json('included_services')->nullable();
            $table->unsignedInteger('included_support_minutes')->default(0);
            $table->unsignedInteger('used_support_minutes')->default(0);
            $table->decimal('additional_hourly_rate', 12, 2)->default(0);
            $table->date('start_date');
            $table->date('next_billing_date')->nullable()->index();
            $table->string('status')->default('pending')->index();
            $table->timestamp('last_backup_at')->nullable();
            $table->timestamp('last_maintenance_at')->nullable();
            $table->string('ssl_status')->default('not_checked');
            $table->string('service_status')->default('unknown');
            $table->timestamps();
        });

        Schema::create('care_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('care_plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');
            $table->unsignedInteger('minutes')->default(0);
            $table->text('notes')->nullable();
            $table->dateTime('occurred_at');
            $table->timestamps();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('care_plan_id')->nullable()->after('project_id')->constrained()->restrictOnDelete();
            $table->unique(['care_plan_id', 'issue_date']);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['care_plan_id', 'issue_date']);
            $table->dropConstrainedForeignId('care_plan_id');
        });
        Schema::dropIfExists('care_activities');
        Schema::dropIfExists('care_plans');
    }
};
