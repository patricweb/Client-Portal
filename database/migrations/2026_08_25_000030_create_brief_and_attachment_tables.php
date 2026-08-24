<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brief_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('project_type')->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('brief_template_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brief_template_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->string('type')->default('textarea');
            $table->text('help_text')->nullable();
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('position');
            $table->timestamps();
            $table->unique(['brief_template_id', 'key']);
        });

        Schema::create('project_briefs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('brief_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('draft')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('brief_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_brief_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brief_template_field_id')->nullable()->constrained()->nullOnDelete();
            $table->longText('value')->nullable();
            $table->timestamps();
            $table->unique(['project_brief_id', 'brief_template_field_id']);
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->nullableMorphs('attachable');
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('brief_answers');
        Schema::dropIfExists('project_briefs');
        Schema::dropIfExists('brief_template_fields');
        Schema::dropIfExists('brief_templates');
    }
};
