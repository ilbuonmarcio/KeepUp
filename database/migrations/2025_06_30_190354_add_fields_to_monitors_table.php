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
            $table->string('operating_system')->nullable(true)->after('ssh_private_key');
            $table->string('uptime')->nullable(true)->after('operating_system');
            $table->string('updates_available')->nullable(true)->after('uptime');
            $table->string('ip_addresses')->nullable(true)->after('updates_available');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->dropColumn(['operating_system', 'uptime', 'updates_available', 'ip_addresses']);
        });
    }
};
