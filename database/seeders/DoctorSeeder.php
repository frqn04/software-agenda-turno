<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Doctor;
use App\Models\Especialidad;
use Illuminate\Support\Facades\Hash;

class DoctorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Asegurar que tenemos especialidades
        $cardiologia = Especialidad::where('nombre', 'Cardiología')->first();
        $dermatologia = Especialidad::where('nombre', 'Dermatología')->first();
        $pediatria = Especialidad::where('nombre', 'Pediatría')->first();

        if (!$cardiologia || !$dermatologia || !$pediatria) {
            $this->command->info('Ejecutando EspecialidadSeeder primero...');
            $this->call(EspecialidadSeeder::class);
            
            $cardiologia = Especialidad::where('nombre', 'Cardiología')->first();
            $dermatologia = Especialidad::where('nombre', 'Dermatología')->first();
            $pediatria = Especialidad::where('nombre', 'Pediatría')->first();
        }

        $doctores = [
            [
                'nombre' => 'Carlos',
                'apellido' => 'Rodriguez',
                'matricula' => 'MP001',
                'telefono' => '+54 11 1234-5678',
                'email' => 'carlos.rodriguez@hospital.com',
                'especialidad_id' => $cardiologia->id,
                'activo' => true,
                'user' => [
                    'name' => 'Dr. Carlos Rodriguez',
                    'email' => 'carlos.rodriguez@hospital.com',
                    'password' => Hash::make('password123'),
                    'rol' => 'doctor',
                    'activo' => true,
                ]
            ],
            [
                'nombre' => 'María',
                'apellido' => 'González',
                'matricula' => 'MP002',
                'telefono' => '+54 11 2345-6789',
                'email' => 'maria.gonzalez@hospital.com',
                'especialidad_id' => $dermatologia->id,
                'activo' => true,
                'user' => [
                    'name' => 'Dra. María González',
                    'email' => 'maria.gonzalez@hospital.com',
                    'password' => Hash::make('password123'),
                    'rol' => 'doctor',
                    'activo' => true,
                ]
            ],
            [
                'nombre' => 'Ana',
                'apellido' => 'López',
                'matricula' => 'MP003',
                'telefono' => '+54 11 3456-7890',
                'email' => 'ana.lopez@hospital.com',
                'especialidad_id' => $pediatria->id,
                'activo' => true,
                'user' => [
                    'name' => 'Dra. Ana López',
                    'email' => 'ana.lopez@hospital.com',
                    'password' => Hash::make('password123'),
                    'rol' => 'doctor',
                    'activo' => true,
                ]
            ],
        ];

        foreach ($doctores as $doctorData) {
            // Crear o encontrar el doctor
            $doctor = Doctor::firstOrCreate(
                ['matricula' => $doctorData['matricula']],
                [
                    'nombre' => $doctorData['nombre'],
                    'apellido' => $doctorData['apellido'],
                    'telefono' => $doctorData['telefono'],
                    'email' => $doctorData['email'],
                    'especialidad_id' => $doctorData['especialidad_id'],
                    'activo' => $doctorData['activo'],
                ]
            );

            // Crear el usuario asociado
            $userData = $doctorData['user'];
            $userData['doctor_id'] = $doctor->id;
            
            User::firstOrCreate(
                ['email' => $userData['email']],
                $userData
            );

            $this->command->info("Doctor {$doctor->nombre} {$doctor->apellido} creado/actualizado");
        }
    }
}
