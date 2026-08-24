<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('project_type')->index();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('workflow_template_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_template_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('client_description')->nullable();
            $table->unsignedInteger('position');
            $table->boolean('requires_approval')->default(false);
            $table->timestamps();
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('workflow_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('type')->index();
            $table->text('description')->nullable();
            $table->longText('scope')->nullable();
            $table->longText('exclusions')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->char('currency', 3)->default('USD');
            $table->string('status')->default('draft')->index();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->date('start_date')->nullable();
            $table->date('target_completion_date')->nullable();
            $table->string('staging_url')->nullable();
            $table->string('production_url')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('project_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('client_description')->nullable();
            $table->text('internal_description')->nullable();
            $table->unsignedInteger('position');
            $table->string('status')->default('not_started')->index();
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->boolean('requires_approval')->default(false);
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_stages');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('workflow_template_stages');
        Schema::dropIfExists('workflow_templates');
    }
};
