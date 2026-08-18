<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitors_versioning', function (Blueprint $table) {
            $table->float('uptime')->nullable()->default(null)->change();
            $table->integer('updates_available')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        DB::table('monitors_versioning')->whereNull('uptime')->update(['uptime' => 0]);
        DB::table('monitors_versioning')->whereNull('updates_available')->update(['updates_available' => 0]);

        Schema::table('monitors_versioning', function (Blueprint $table) {
            $table->float('uptime')->nullable(false)->default(0)->change();
            $table->integer('updates_available')->nullable(false)->default(0)->change();
        });
    }
};
