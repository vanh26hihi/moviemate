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
        Schema::create('seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->string('row');
            $table->integer('number');
            $table->string('seat_code');
            $table->enum('type', ['normal', 'vip', 'couple'])->default('normal');
            $table->enum('status', ['active', 'maintenance'])->default('active');
            $table->timestamps();

            $table->unique(['room_id', 'seat_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seats');
    }
};