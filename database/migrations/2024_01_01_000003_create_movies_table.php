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
        Schema::create('movies', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('poster')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('trailer_url')->nullable();
            $table->string('country')->nullable();
            $table->integer('duration')->default(90);
            $table->string('age_rating')->default('P');
            $table->date('release_date')->nullable();
            $table->enum('status', ['now_showing', 'coming_soon', 'stopped'])->default('now_showing');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movies');
    }
};