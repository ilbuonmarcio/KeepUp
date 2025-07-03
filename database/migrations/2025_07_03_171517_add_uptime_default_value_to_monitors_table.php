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
            $table->float('uptime')->nullable(true)->change();
            $table->integer('updates_available')->nullable(true)->change();
            $table->integer('threshold_updates_available')->default(1)->nullable(false)->change(); // Fix default 0 erroneously entered before...
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->float('uptime')->nullable(false)->change();
            $table->float('uptime')->nullable(false)->change();
            $table->integer('threshold_updates_available')->default(0)->nullable(false)->change();
        });
    }
};
