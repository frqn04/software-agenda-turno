<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Paciente;
use App\Models\Doctor;
use App\Models\Especialidad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class TurnoApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Crear especialidad de prueba
        $this->especialidad = Especialidad::create([
            'nombre' => 'Cardiología',
            'descripcion' => 'Especialidad del corazón',
            'activo' => true,
        ]);

        // Crear doctor de prueba
        $this->doctor = Doctor::create([
            'nombre' => 'Dr. Juan',
            'apellido' => 'Pérez',
            'especialidad_id' => $this->especialidad->id,
            'matricula' => '12345',
            'telefono' => '123456789',
            'email' => 'doctor@test.com',
            'activo' => true,
        ]);

        // Crear paciente de prueba
        $this->paciente = Paciente::create([
            'nombre' => 'Ana',
            'apellido' => 'García',
            'dni' => '12345678',
            'fecha_nacimiento' => '1990-01-01',
            'telefono' => '987654321',
            'email' => 'paciente@test.com',
            'activo' => true,
        ]);

        // Crear usuario admin de prueba
        $this->adminUser = User::create([
            'name' => 'Admin Test',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'rol' => 'admin',
            'activo' => true,
        ]);
    }

    public function test_can_create_turno_with_valid_data()
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/api/v1/turnos', [
            'paciente_id' => $this->paciente->id,
            'doctor_id' => $this->doctor->id,
            'fecha' => now()->addDays(2)->format('Y-m-d'),
            'hora_inicio' => '10:00',
            'motivo' => 'Consulta de rutina',
            'estado' => 'programado',
        ]);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'turno' => [
                        'id',
                        'paciente_id',
                        'doctor_id',
                        'fecha',
                        'hora_inicio',
                        'motivo',
                        'estado',
                    ]
                ]);

        $this->assertDatabaseHas('turnos', [
            'paciente_id' => $this->paciente->id,
            'doctor_id' => $this->doctor->id,
            'motivo' => 'Consulta de rutina',
        ]);
    }

    public function test_cannot_create_turno_without_authentication()
    {
        $response = $this->postJson('/api/v1/turnos', [
            'paciente_id' => $this->paciente->id,
            'doctor_id' => $this->doctor->id,
            'fecha' => now()->addDays(2)->format('Y-m-d'),
            'hora_inicio' => '10:00',
            'motivo' => 'Consulta de rutina',
        ]);

        $response->assertStatus(401);
    }

    public function test_validates_required_fields()
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/api/v1/turnos', []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors([
                    'paciente_id',
                    'doctor_id',
                    'fecha',
                    'hora_inicio',
                    'motivo'
                ]);
    }

    public function test_can_get_available_slots_for_doctor()
    {
        $fecha = now()->addDays(3)->format('Y-m-d');
        
        $response = $this->getJson("/api/v1/doctores/{$this->doctor->id}/horarios-disponibles/{$fecha}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'horarios_disponibles',
                    'fecha',
                    'doctor_id'
                ]);
    }

    public function test_can_list_turnos_with_pagination()
    {
        Sanctum::actingAs($this->adminUser);

        // Crear algunos turnos de prueba
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/turnos', [
                'paciente_id' => $this->paciente->id,
                'doctor_id' => $this->doctor->id,
                'fecha' => now()->addDays($i + 1)->format('Y-m-d'),
                'hora_inicio' => '10:00',
                'motivo' => "Consulta {$i}",
                'estado' => 'programado',
            ]);
        }

        $response = $this->getJson('/api/v1/turnos?per_page=3');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data',
                    'current_page',
                    'per_page',
                    'total'
                ]);
    }
}
