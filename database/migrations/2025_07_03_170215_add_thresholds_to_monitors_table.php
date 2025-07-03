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
            $table->float('threshold_uptime')->default(365)->nullable(false)->after('uptime');
            $table->integer('threshold_updates_available')->default(0)->nullable(false)->after('updates_available');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->dropColumn(['threshold_uptime', 'threshold_updates_available']);
        });
    }
};
