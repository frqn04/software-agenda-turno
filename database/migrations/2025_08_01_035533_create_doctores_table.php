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
        Schema::create('doctores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('apellido', 100);
            $table->string('matricula', 20)->unique();
            $table->string('telefono', 20)->nullable();
            $table->string('email', 150)->unique();
            $table->foreignId('especialidad_id')->constrained('especialidades')->onDelete('restrict');
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index(['matricula', 'activo']);
            $table->index(['especialidad_id', 'activo']);
            $table->index(['email', 'activo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctores');
    }
};
