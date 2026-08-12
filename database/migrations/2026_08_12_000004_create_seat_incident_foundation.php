<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seat_incidents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cinema_id')->constrained('cinemas')->restrictOnDelete();
            $table->foreignId('room_id')->constrained('rooms')->restrictOnDelete();
            $table->foreignId('reported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['open', 'resolved', 'cancelled'])->default('open');
            $table->enum('reason', ['seat_broken', 'maintenance_required', 'safety_issue', 'other']);
            $table->string('note', 500)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['cinema_id', 'status', 'created_at'], 'seat_incidents_cinema_status_created_index');
            $table->index(['room_id', 'status', 'created_at'], 'seat_incidents_room_status_created_index');
        });

        Schema::create('seat_incident_seats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seat_incident_id')->constrained('seat_incidents')->restrictOnDelete();
            $table->foreignId('seat_id')->constrained('seats')->restrictOnDelete();
            $table->string('active_lock_key', 16)->nullable();
            $table->timestamps();

            $table->unique(['seat_incident_id', 'seat_id'], 'seat_incident_seat_unique');
            $table->unique(['seat_id', 'active_lock_key'], 'seat_incident_active_seat_unique');
            $table->index(['seat_incident_id', 'id'], 'seat_incident_seats_incident_index');
        });

        Schema::create('seat_incident_impacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seat_incident_id')->constrained('seat_incidents')->restrictOnDelete();
            $table->foreignId('booking_seat_id')->constrained('booking_seats')->restrictOnDelete();
            $table->enum('detected_classification', ['ordinary_hold', 'retained_payment', 'paid', 'released']);
            $table->enum('resolution_status', ['unresolved', 'resolved'])->default('unresolved');
            $table->timestamp('detected_at');
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolution_reason', 80)->nullable();
            $table->timestamps();

            $table->unique(['seat_incident_id', 'booking_seat_id'], 'seat_incident_impact_unique');
            $table->index(['seat_incident_id', 'resolution_status'], 'seat_incident_impacts_resolution_index');
            $table->index(['detected_classification', 'resolution_status'], 'seat_incident_impacts_classification_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seat_incident_impacts');
        Schema::dropIfExists('seat_incident_seats');
        Schema::dropIfExists('seat_incidents');
    }
};
