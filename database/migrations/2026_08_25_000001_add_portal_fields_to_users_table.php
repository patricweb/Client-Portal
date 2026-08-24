<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id');
            $table->string('role')->default('client')->after('password');
            $table->string('status')->default('invited')->after('role');
            $table->boolean('must_change_password')->default(true)->after('status');
            $table->timestamp('last_login_at')->nullable()->after('must_change_password');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn([
            'company_id', 'role', 'status', 'must_change_password', 'last_login_at',
        ]));
    }
};
