<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category')->index();
            $table->string('client_priority')->default('normal');
            $table->string('internal_priority')->default('normal')->index();
            $table->string('status')->default('new')->index();
            $table->string('billing_classification')->nullable()->index();
            $table->string('subject');
            $table->longText('description');
            $table->unsignedInteger('estimated_minutes')->nullable();
            $table->unsignedInteger('care_minutes_used')->default(0);
            $table->timestamp('care_minutes_applied_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('request_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->longText('body');
            $table->boolean('is_internal')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('external_communications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('support_request_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel');
            $table->text('summary');
            $table->dateTime('occurred_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_communications');
        Schema::dropIfExists('request_messages');
        Schema::dropIfExists('support_requests');
    }
};
