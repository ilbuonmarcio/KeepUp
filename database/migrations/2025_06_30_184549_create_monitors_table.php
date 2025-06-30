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
        Schema::create('monitors', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable(false);
            $table->string('hostname_ip')->nullable(false);
            $table->string('username')->nullable(false);
            $table->string('auth_method')->nullable(false);
            $table->string('password')->nullable(true);
            $table->string('ssh_private_key')->nullable(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monitors');
    }
};
