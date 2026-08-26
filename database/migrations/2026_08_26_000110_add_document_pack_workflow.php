<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_profiles', function (Blueprint $table) {
            $table->id();
            $table->json('details');
            $table->timestamps();
        });
        DB::table('provider_profiles')->insert([
            'id' => 1, 'details' => json_encode(['legal_name' => 'Matei Patric', 'brand_name' => 'Ikira', 'country' => 'Republic of Moldova', 'currency' => 'USD', 'payment_due_days' => 7]),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Schema::table('documents', function (Blueprint $table) {
            $table->string('document_number')->nullable()->unique();
            $table->string('pack_template')->nullable();
            $table->foreignId('parent_document_id')->nullable()->constrained('documents')->restrictOnDelete();
        });
        Schema::table('document_versions', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('pdf_sha256', 64)->nullable();
        });
        Schema::table('attachments', function (Blueprint $table) {
            $table->foreignId('document_version_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->json('snapshot')->nullable();
            $table->string('kind')->default('standard');
            $table->foreignId('sow_document_id')->nullable()->constrained('documents')->restrictOnDelete();
            $table->foreignId('acceptance_document_id')->nullable()->constrained('documents')->restrictOnDelete();
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->text('tax_description')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('pdf_sha256', 64)->nullable();
        });
        // Preserve publication evidence for existing versions; do not rewrite their content.
        DB::table('documents')->whereNotNull('sent_at')->orderBy('id')->each(function ($document) {
            DB::table('document_versions')->where('document_id', $document->id)
                ->where('version', $document->current_version)->update(['published_at' => $document->sent_at, 'signed_at' => $document->signed_at]);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sow_document_id');
            $table->dropConstrainedForeignId('acceptance_document_id');
            $table->dropColumn(['snapshot', 'kind', 'tax_amount', 'tax_description', 'pdf_path', 'pdf_sha256']);
        });
        Schema::table('attachments', fn (Blueprint $table) => $table->dropConstrainedForeignId('document_version_id'));
        Schema::table('document_versions', fn (Blueprint $table) => $table->dropColumn(['published_at', 'signed_at', 'pdf_path', 'pdf_sha256']));
        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_document_id');
            $table->dropColumn(['document_number', 'pack_template']);
        });
        Schema::dropIfExists('provider_profiles');
    }
};
