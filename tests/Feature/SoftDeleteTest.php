<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Doctor;
use App\Models\Paciente;
use App\Models\Turno;
use App\Models\Especialidad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class SoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_soft_deletes_patients()
    {
        $paciente = Paciente::factory()->create([
            'nombre' => 'Test',
            'apellido' => 'Patient',
            'dni' => '12345678'
        ]);

        $paciente->delete();

        // El paciente debería estar soft deleted
        $this->assertSoftDeleted('pacientes', ['id' => $paciente->id]);
        
        // No debería aparecer en consultas normales
        $this->assertDatabaseMissing('pacientes', [
            'id' => $paciente->id,
            'deleted_at' => null
        ]);
    }

    /** @test */
    public function it_soft_deletes_doctors()
    {
        $especialidad = Especialidad::factory()->create();
        
        $doctor = Doctor::factory()->create([
            'especialidad_id' => $especialidad->id,
            'nombre' => 'Dr. Test',
            'matricula' => 'TEST123'
        ]);

        $doctor->delete();

        // El doctor debería estar soft deleted
        $this->assertSoftDeleted('doctores', ['id' => $doctor->id]);
    }

    /** @test */
    public function it_soft_deletes_appointments()
    {
        $especialidad = Especialidad::factory()->create();
        $doctor = Doctor::factory()->create(['especialidad_id' => $especialidad->id]);
        $paciente = Paciente::factory()->create();

        $turno = Turno::factory()->create([
            'doctor_id' => $doctor->id,
            'paciente_id' => $paciente->id,
            'fecha' => today(),
            'hora_inicio' => '10:00:00',
            'hora_fin' => '11:00:00',
        ]);

        $turno->delete();

        // El turno debería estar soft deleted
        $this->assertSoftDeleted('turnos', ['id' => $turno->id]);
    }

    /** @test */
    public function it_can_restore_soft_deleted_models()
    {
        $paciente = Paciente::factory()->create();
        $paciente->delete();

        // Restaurar el paciente
        $paciente->restore();

        // El paciente debería estar activo nuevamente
        $this->assertDatabaseHas('pacientes', [
            'id' => $paciente->id,
            'deleted_at' => null
        ]);
    }

    /** @test */
    public function it_includes_soft_deleted_with_trashed()
    {
        $paciente = Paciente::factory()->create();
        $paciente->delete();

        // Consulta normal no debería encontrarlo
        $this->assertEquals(0, Paciente::count());

        // Consulta con trashed debería encontrarlo
        $this->assertEquals(1, Paciente::withTrashed()->count());
        $this->assertEquals(1, Paciente::onlyTrashed()->count());
    }

    /** @test */
    public function it_force_deletes_permanently()
    {
        $paciente = Paciente::factory()->create();
        $pacienteId = $paciente->id;

        $paciente->forceDelete();

        // El paciente debería estar completamente eliminado de la base de datos
        $this->assertDatabaseMissing('pacientes', ['id' => $pacienteId]);
        $this->assertEquals(0, Paciente::withTrashed()->count());
    }

    /** @test */
    public function soft_deleted_appointments_dont_cause_overlaps()
    {
        $especialidad = Especialidad::factory()->create();
        $doctor = Doctor::factory()->create(['especialidad_id' => $especialidad->id]);
        $paciente = Paciente::factory()->create();

        // Crear y eliminar un turno
        $turno1 = Turno::factory()->create([
            'doctor_id' => $doctor->id,
            'paciente_id' => $paciente->id,
            'fecha' => today(),
            'hora_inicio' => '10:00:00',
            'hora_fin' => '11:00:00',
            'estado' => 'programado',
        ]);

        $turno1->delete();

        // Crear otro turno en el mismo horario debería ser posible
        $turno2 = Turno::factory()->create([
            'doctor_id' => $doctor->id,
            'paciente_id' => $paciente->id,
            'fecha' => today(),
            'hora_inicio' => '10:00:00',
            'hora_fin' => '11:00:00',
            'estado' => 'programado',
        ]);

        $this->assertDatabaseHas('turnos', [
            'id' => $turno2->id,
            'deleted_at' => null
        ]);
    }
}
