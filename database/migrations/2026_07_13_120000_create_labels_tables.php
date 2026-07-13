<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('labels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('normalized_name', 50)->unique();
            $table->timestamps();
        });

        Schema::create('label_monitor', function (Blueprint $table) {
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('label_id')->constrained()->cascadeOnDelete();
            $table->primary(['monitor_id', 'label_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('label_monitor');
        Schema::dropIfExists('labels');
    }
};
