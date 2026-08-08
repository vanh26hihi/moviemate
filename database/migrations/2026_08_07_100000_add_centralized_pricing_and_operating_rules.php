<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cinemas', function (Blueprint $table): void {
            $table->unsignedSmallInteger('default_cleaning_buffer_minutes')->nullable()->after('timezone');
        });

        Schema::table('rooms', function (Blueprint $table): void {
            $table->unsignedSmallInteger('cleaning_buffer_minutes')->nullable()->after('total_seats');
        });

        Schema::table('showtimes', function (Blueprint $table): void {
            $table->string('pricing_version', 32)->nullable()->after('vip_price');
        });

        Schema::create('cinema_operating_hours', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cinema_id')->constrained('cinemas')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('opens_at')->nullable();
            $table->time('latest_show_start_at')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->timestamps();
            $table->unique(['cinema_id', 'day_of_week']);
        });

        Schema::create('cinema_pricing_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('rule_type', 32);
            $table->foreignId('cinema_id')->nullable()->constrained('cinemas')->cascadeOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->cascadeOnDelete();
            $table->string('seat_type', 20)->nullable();
            $table->string('room_type', 20)->nullable();
            $table->json('days_of_week')->nullable();
            $table->date('date_start')->nullable();
            $table->date('date_end')->nullable();
            $table->time('time_start')->nullable();
            $table->time('time_end')->nullable();
            $table->bigInteger('amount_vnd');
            $table->integer('priority')->default(0);
            $table->boolean('stacks_with_weekend')->default(false);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->string('status', 16)->default('active');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'rule_type', 'cinema_id', 'room_id'], 'pricing_rule_match_index');
            $table->index(['starts_at', 'ends_at'], 'pricing_rule_effective_index');
            $table->index(['date_start', 'date_end'], 'pricing_rule_date_index');
        });

        Schema::table('booking_seats', function (Blueprint $table): void {
            $table->string('pricing_unit_key')->nullable()->after('price');
            $table->string('pricing_unit_label')->nullable()->after('pricing_unit_key');
            $table->string('seat_type_snapshot', 20)->nullable()->after('pricing_unit_label');
            $table->unsignedBigInteger('base_amount')->nullable()->after('seat_type_snapshot');
            $table->bigInteger('surcharge_total')->nullable()->after('base_amount');
            $table->unsignedBigInteger('final_unit_amount')->nullable()->after('surcharge_total');
            $table->json('pricing_breakdown')->nullable()->after('final_unit_amount');
            $table->string('pricing_fingerprint', 64)->nullable()->after('pricing_breakdown');
        });
    }

    public function down(): void
    {
        Schema::table('booking_seats', function (Blueprint $table): void {
            $table->dropColumn([
                'pricing_unit_key', 'pricing_unit_label', 'seat_type_snapshot', 'base_amount',
                'surcharge_total', 'final_unit_amount', 'pricing_breakdown', 'pricing_fingerprint',
            ]);
        });
        Schema::dropIfExists('cinema_pricing_rules');
        Schema::dropIfExists('cinema_operating_hours');
        Schema::table('showtimes', fn (Blueprint $table) => $table->dropColumn('pricing_version'));
        Schema::table('rooms', fn (Blueprint $table) => $table->dropColumn('cleaning_buffer_minutes'));
        Schema::table('cinemas', fn (Blueprint $table) => $table->dropColumn('default_cleaning_buffer_minutes'));
    }
};
