<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->longText('description')->nullable();
            $table->string('discipline')->default('other')->index();
            $table->string('status')->default('new')->index();
            $table->string('priority')->default('normal')->index();
            $table->decimal('price', 12, 2)->nullable();
            $table->char('currency', 3)->default('USD');
            $table->date('due_date')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('telegram_chat_id')->nullable();
            $table->string('telegram_message_id')->nullable();
            $table->string('discord_forum_id')->nullable();
            $table->string('discord_thread_id')->nullable();
            $table->string('discord_message_id')->nullable();
            $table->string('channel_sync_status')->default('pending')->index();
            $table->text('channel_sync_error')->nullable();
            $table->timestamp('last_channel_sync_at')->nullable();
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['assigned_to', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_items');
    }
};
