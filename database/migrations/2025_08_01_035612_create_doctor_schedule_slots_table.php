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
        Schema::create('doctor_schedule_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained('doctores')->onDelete('cascade');
            $table->integer('day_of_week'); // 0=Domingo, 1=Lunes, etc.
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('slot_duration_minutes')->default(30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index(['doctor_id', 'day_of_week', 'is_active']);
            $table->index(['day_of_week', 'start_time', 'end_time']);
            
            // Evitar duplicados con nombre de índice corto
            $table->unique(['doctor_id', 'day_of_week', 'start_time', 'end_time'], 'unique_doctor_schedule');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctor_schedule_slots');
    }
};
