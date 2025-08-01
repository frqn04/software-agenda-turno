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
        Schema::create('doctor_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained('doctores')->onDelete('cascade');
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->decimal('tarifa_consulta', 10, 2);
            $table->integer('duracion_consulta_minutos')->default(30);
            $table->time('hora_inicio_manana')->nullable();
            $table->time('hora_fin_manana')->nullable();
            $table->time('hora_inicio_tarde')->nullable();
            $table->time('hora_fin_tarde')->nullable();
            $table->json('dias_trabajo')->nullable(); // ['lunes', 'martes', etc.]
            $table->json('horario_especial')->nullable(); // Horarios especiales por fecha
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index(['doctor_id', 'is_active']);
            $table->index(['fecha_inicio', 'fecha_fin']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctor_contracts');
    }
};
