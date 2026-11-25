<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('windows_domains', function (Blueprint $table) {
            $table->boolean('enabled')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('windows_domains', function (Blueprint $table) {
            $table->dropColumn('enabled');
        });
    }
};
