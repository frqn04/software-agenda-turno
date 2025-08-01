<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Especialidad;

class EspecialidadSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $especialidades = [
            [
                'nombre' => 'Cardiología',
                'descripcion' => 'Especialidad médica dedicada al diagnóstico y tratamiento de las enfermedades del corazón y del sistema circulatorio.',
                'activo' => true,
            ],
            [
                'nombre' => 'Dermatología',
                'descripcion' => 'Especialidad médica que se dedica al diagnóstico y tratamiento de enfermedades de la piel.',
                'activo' => true,
            ],
            [
                'nombre' => 'Gastroenterología',
                'descripcion' => 'Especialidad médica que estudia el tracto gastrointestinal y hepatobiliar.',
                'activo' => true,
            ],
            [
                'nombre' => 'Ginecología',
                'descripcion' => 'Especialidad médica que trata las enfermedades del sistema reproductor femenino.',
                'activo' => true,
            ],
            [
                'nombre' => 'Neurología',
                'descripcion' => 'Especialidad médica que trata los trastornos del sistema nervioso.',
                'activo' => true,
            ],
            [
                'nombre' => 'Oftalmología',
                'descripcion' => 'Especialidad médica que estudia las enfermedades de los ojos y su tratamiento.',
                'activo' => true,
            ],
            [
                'nombre' => 'Pediatría',
                'descripcion' => 'Especialidad médica que estudia al niño y sus enfermedades.',
                'activo' => true,
            ],
            [
                'nombre' => 'Psiquiatría',
                'descripcion' => 'Especialidad médica dedicada al estudio de los trastornos mentales.',
                'activo' => true,
            ],
            [
                'nombre' => 'Traumatología',
                'descripcion' => 'Especialidad médica que se dedica al estudio de las lesiones del aparato locomotor.',
                'activo' => true,
            ],
            [
                'nombre' => 'Urología',
                'descripcion' => 'Especialidad médica que se ocupa del estudio, diagnóstico y tratamiento de las patologías que afectan al aparato urinario.',
                'activo' => true,
            ],
        ];

        foreach ($especialidades as $especialidad) {
            Especialidad::firstOrCreate(
                ['nombre' => $especialidad['nombre']],
                $especialidad
            );
        }
    }
}
