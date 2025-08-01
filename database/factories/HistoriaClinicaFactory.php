<?php

namespace Database\Factories;

use App\Models\HistoriaClinica;
use App\Models\Doctor;
use App\Models\Paciente;
use App\Models\Turno;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\HistoriaClinica>
 */
class HistoriaClinicaFactory extends Factory
{
    protected $model = HistoriaClinica::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'paciente_id' => Paciente::factory(),
            'doctor_id' => Doctor::factory(),
            'turno_id' => Turno::factory(),
            'fecha_consulta' => $this->faker->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
            'motivo_consulta' => $this->faker->sentence(),
            'antecedentes' => $this->faker->optional()->paragraph(),
            'examen_fisico' => $this->faker->optional()->paragraph(),
            'diagnostico' => $this->faker->optional()->sentence(),
            'tratamiento' => $this->faker->optional()->paragraph(),
            'observaciones' => $this->faker->optional()->paragraph(),
            'signos_vitales' => json_encode([
                'presion_arterial' => $this->faker->randomElement(['120/80', '130/85', '110/70']),
                'pulso' => $this->faker->numberBetween(60, 100),
                'temperatura' => $this->faker->randomFloat(1, 36.0, 37.5),
                'peso' => $this->faker->randomFloat(1, 50.0, 120.0),
                'altura' => $this->faker->randomFloat(2, 1.50, 2.00),
            ]),
        ];
    }

    /**
     * Create a historia clinica for a specific patient.
     */
    public function forPaciente(Paciente $paciente): static
    {
        return $this->state(fn (array $attributes) => [
            'paciente_id' => $paciente->id,
        ]);
    }

    /**
     * Create a historia clinica for a specific doctor.
     */
    public function forDoctor(Doctor $doctor): static
    {
        return $this->state(fn (array $attributes) => [
            'doctor_id' => $doctor->id,
        ]);
    }

    /**
     * Create a historia clinica for a specific turno.
     */
    public function forTurno(Turno $turno): static
    {
        return $this->state(fn (array $attributes) => [
            'turno_id' => $turno->id,
            'paciente_id' => $turno->paciente_id,
            'doctor_id' => $turno->doctor_id,
            'fecha_consulta' => $turno->fecha,
        ]);
    }

    /**
     * Create a historia clinica for today.
     */
    public function today(): static
    {
        return $this->state(fn (array $attributes) => [
            'fecha_consulta' => today()->format('Y-m-d'),
        ]);
    }

    /**
     * Create a historia clinica with complete data.
     */
    public function complete(): static
    {
        return $this->state(fn (array $attributes) => [
            'antecedentes' => $this->faker->paragraph(),
            'examen_fisico' => $this->faker->paragraph(),
            'diagnostico' => $this->faker->sentence(),
            'tratamiento' => $this->faker->paragraph(),
            'observaciones' => $this->faker->paragraph(),
        ]);
    }
}
