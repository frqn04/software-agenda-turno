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
        Schema::create('historias_clinicas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paciente_id')->constrained('pacientes')->onDelete('cascade');
            $table->foreignId('doctor_id')->constrained('doctores')->onDelete('restrict');
            $table->foreignId('turno_id')->nullable()->constrained('turnos')->onDelete('set null');
            $table->date('fecha_consulta');
            $table->text('motivo_consulta');
            $table->text('antecedentes')->nullable();
            $table->text('examen_fisico')->nullable();
            $table->text('diagnostico')->nullable();
            $table->text('tratamiento')->nullable();
            $table->text('observaciones')->nullable();
            $table->json('signos_vitales')->nullable(); // {presion: '', pulso: '', temperatura: ''}
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index(['paciente_id', 'fecha_consulta']);
            $table->index(['doctor_id', 'fecha_consulta']);
            $table->index(['turno_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historias_clinicas');
    }
};
