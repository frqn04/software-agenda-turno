<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Doctor;
use App\Models\Paciente;
use App\Models\Turno;
use App\Models\DoctorContract;
use App\Models\Especialidad;
use App\Services\AppointmentValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class AppointmentValidationTest extends TestCase
{
    use RefreshDatabase;

    protected $validationService;
    protected $doctor;
    protected $paciente;
    protected $especialidad;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->validationService = new AppointmentValidationService();
        
        // Crear datos de prueba
        $this->especialidad = Especialidad::factory()->create([
            'nombre' => 'Odontología General'
        ]);
        
        $this->doctor = Doctor::factory()->create([
            'especialidad_id' => $this->especialidad->id,
            'nombre' => 'Dr. Test',
            'apellido' => 'Doctor',
            'matricula' => 'TEST123'
        ]);
        
        $this->paciente = Paciente::factory()->create([
            'nombre' => 'Test',
            'apellido' => 'Patient',
            'dni' => '12345678'
        ]);

        // Crear contrato activo
        DoctorContract::factory()->create([
            'doctor_id' => $this->doctor->id,
            'fecha_inicio' => now()->subDays(30),
            'fecha_fin' => now()->addDays(30),
            'is_active' => true,
        ]);
    }

    /** @test */
    public function it_detects_appointment_overlap()
    {
        // Crear un turno existente
        Turno::factory()->create([
            'doctor_id' => $this->doctor->id,
            'paciente_id' => $this->paciente->id,
            'fecha' => today(),
            'hora_inicio' => '10:00:00',
            'hora_fin' => '11:00:00',
            'estado' => 'programado',
        ]);

        // Intentar crear otro turno que se superpone
        $hasOverlap = !$this->validationService->validateNoOverlap(
            $this->doctor->id,
            today()->format('Y-m-d'),
            '10:30:00',
            '11:30:00'
        );

        $this->assertTrue($hasOverlap, 'Debería detectar superposición de turnos');
    }

    /** @test */
    public function it_allows_non_overlapping_appointments()
    {
        // Crear un turno existente
        Turno::factory()->create([
            'doctor_id' => $this->doctor->id,
            'paciente_id' => $this->paciente->id,
            'fecha' => today(),
            'hora_inicio' => '10:00:00',
            'hora_fin' => '11:00:00',
            'estado' => 'programado',
        ]);

        // Crear turno que no se superpone
        $hasOverlap = !$this->validationService->validateNoOverlap(
            $this->doctor->id,
            today()->format('Y-m-d'),
            '11:00:00',
            '12:00:00'
        );

        $this->assertFalse($hasOverlap, 'No debería detectar superposición');
    }

    /** @test */
    public function it_validates_appointment_within_active_contract()
    {
        $isWithinContract = $this->validationService->validateWithinContract(
            $this->doctor->id,
            today()->format('Y-m-d')
        );

        $this->assertTrue($isWithinContract, 'El turno debería estar dentro del contrato activo');
    }

    /** @test */
    public function it_rejects_appointment_outside_contract_period()
    {
        $futureDate = now()->addDays(60)->format('Y-m-d');
        
        $isWithinContract = $this->validationService->validateWithinContract(
            $this->doctor->id,
            $futureDate
        );

        $this->assertFalse($isWithinContract, 'El turno no debería estar dentro del período del contrato');
    }

    /** @test */
    public function it_excludes_current_appointment_from_overlap_check()
    {
        // Crear un turno
        $turno = Turno::factory()->create([
            'doctor_id' => $this->doctor->id,
            'paciente_id' => $this->paciente->id,
            'fecha' => today(),
            'hora_inicio' => '10:00:00',
            'hora_fin' => '11:00:00',
            'estado' => 'programado',
        ]);

        // Verificar que puede "actualizar" el mismo turno sin conflicto
        $hasOverlap = !$this->validationService->validateNoOverlap(
            $this->doctor->id,
            today()->format('Y-m-d'),
            '10:00:00',
            '11:00:00',
            $turno->id // Excluir el turno actual
        );

        $this->assertFalse($hasOverlap, 'No debería detectar superposición al excluir el turno actual');
    }

    /** @test */
    public function it_validates_all_appointment_rules()
    {
        $appointmentData = [
            'doctor_id' => $this->doctor->id,
            'paciente_id' => $this->paciente->id,
            'fecha' => today()->format('Y-m-d'),
            'hora_inicio' => '14:00:00',
            'hora_fin' => '15:00:00',
        ];

        $errors = $this->validationService->validateAppointment($appointmentData);

        // Solo debería fallar la validación de horario (no tenemos horarios configurados)
        $this->assertContains('El turno no está dentro del horario disponible del doctor', $errors);
    }

    /** @test */
    public function it_calculates_end_time_correctly()
    {
        $startTime = '10:00:00';
        $duration = 30; // minutos
        
        $endTime = $this->validationService->calculateEndTime($startTime, $duration);
        
        $this->assertEquals('10:30:00', $endTime);
    }
}
