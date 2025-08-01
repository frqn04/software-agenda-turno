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
        Schema::create('turnos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained('doctores')->onDelete('restrict');
            $table->foreignId('paciente_id')->constrained('pacientes')->onDelete('restrict');
            $table->date('fecha');
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->integer('duration_minutes')->default(30);
            $table->enum('estado', ['programado', 'confirmado', 'completado', 'cancelado', 'no_show'])->default('programado');
            $table->text('motivo_consulta')->nullable();
            $table->text('observaciones')->nullable();
            $table->decimal('monto', 10, 2)->nullable();
            $table->boolean('pagado')->default(false);
            $table->timestamps();
            $table->softDeletes();
            
            // Índices para performance
            $table->index(['doctor_id', 'fecha', 'deleted_at']);
            $table->index(['paciente_id', 'fecha', 'deleted_at']);
            $table->index(['estado', 'fecha']);
            $table->index(['fecha', 'hora_inicio', 'hora_fin']);
            
            // Evitar duplicados de turnos activos con nombre corto
            $table->unique(['doctor_id', 'fecha', 'hora_inicio', 'deleted_at'], 'unique_active_turnos');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('turnos');
    }
};
