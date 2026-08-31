<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('jurisdiction')->nullable()->after('billing_name');
        });

        Schema::table('approvals', function (Blueprint $table) {
            $table->json('evidence')->nullable()->after('comment');
        });
    }

    public function down(): void
    {
        Schema::table('approvals', fn (Blueprint $table) => $table->dropColumn('evidence'));
        Schema::table('companies', fn (Blueprint $table) => $table->dropColumn('jurisdiction'));
    }
};
