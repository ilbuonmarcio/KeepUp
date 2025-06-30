<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->datetime('latest_successful_check')->nullable(true)->after('ip_addresses');
            $table->integer('latest_check_positive')->nullable(false)->default(0)->after('latest_successful_check');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->dropColumn(['latest_successful_check', 'latest_check_positive']);
        });
    }
};
