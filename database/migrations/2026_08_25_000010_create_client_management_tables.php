<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('contact_name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('source')->nullable();
            $table->string('service')->nullable();
            $table->decimal('estimated_budget', 12, 2)->nullable();
            $table->string('status')->default('new')->index();
            $table->date('next_contact_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('billing_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->text('billing_address')->nullable();
            $table->string('timezone')->default('America/New_York');
            $table->char('currency', 3)->default('USD');
            $table->text('internal_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
        });

        Schema::create('company_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('job_title')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->unique(['company_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_contacts');
        Schema::table('users', fn (Blueprint $table) => $table->dropForeign(['company_id']));
        Schema::dropIfExists('companies');
        Schema::dropIfExists('leads');
    }
};
