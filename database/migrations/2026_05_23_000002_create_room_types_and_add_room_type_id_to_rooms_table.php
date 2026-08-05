<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('status')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->string('room_type')->default('2D')->change();
            $table->foreignId('room_type_id')->nullable()->after('room_type')
                ->constrained('room_types')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropForeign(['room_type_id']);
            $table->dropColumn('room_type_id');
            $table->enum('room_type', ['2D', '3D', 'IMAX'])->default('2D')->change();
        });

        Schema::dropIfExists('room_types');
    }
};
