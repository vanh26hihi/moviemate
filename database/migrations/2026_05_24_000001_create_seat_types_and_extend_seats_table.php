<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seat_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('slug')->nullable()->unique();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->string('color')->nullable();
            $table->string('text_color')->nullable();
            $table->decimal('price_modifier', 10, 2)->default(0);
            $table->boolean('is_pair')->default(false);
            $table->boolean('status')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('seats', function (Blueprint $table) {
            $table->string('status', 30)->default('active')->change();
            $table->foreignId('seat_type_id')->nullable()->after('type')
                ->constrained('seat_types')->nullOnDelete();
            $table->string('pair_code')->nullable()->after('seat_type_id');
            $table->string('row_label')->nullable()->after('pair_code');
            $table->integer('seat_number')->nullable()->after('row_label');
            $table->integer('x_position')->nullable()->after('seat_number');
            $table->integer('y_position')->nullable()->after('x_position');
            $table->boolean('is_center')->default(false)->after('y_position');
        });
    }

    public function down(): void
    {
        Schema::table('seats', function (Blueprint $table) {
            $table->dropForeign(['seat_type_id']);
            $table->dropColumn([
                'seat_type_id',
                'pair_code',
                'row_label',
                'seat_number',
                'x_position',
                'y_position',
                'is_center',
            ]);
            $table->enum('status', ['active', 'maintenance'])->default('active')->change();
        });

        Schema::dropIfExists('seat_types');
    }
};
