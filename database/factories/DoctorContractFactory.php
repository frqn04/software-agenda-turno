<?php

namespace Database\Factories;

use App\Models\DoctorContract;
use App\Models\Doctor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DoctorContract>
 */
class DoctorContractFactory extends Factory
{
    protected $model = DoctorContract::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fechaInicio = $this->faker->dateTimeBetween('-6 months', 'now');
        $fechaFin = $this->faker->optional(0.7)->dateTimeBetween($fechaInicio, '+1 year');

        return [
            'doctor_id' => Doctor::factory(),
            'fecha_inicio' => $fechaInicio->format('Y-m-d'),
            'fecha_fin' => $fechaFin?->format('Y-m-d'),
            'tarifa_consulta' => $this->faker->randomFloat(2, 3000, 15000),
            'duracion_consulta_minutos' => $this->faker->randomElement([30, 45, 60]),
            'hora_inicio_manana' => '08:00:00',
            'hora_fin_manana' => '12:00:00',
            'hora_inicio_tarde' => '14:00:00',
            'hora_fin_tarde' => '18:00:00',
            'dias_trabajo' => json_encode(['lunes', 'martes', 'miercoles', 'jueves', 'viernes']),
            'horario_especial' => null,
            'is_active' => $this->faker->boolean(90), // 90% chance de estar activo
        ];
    }

    /**
     * Indicate that the contract is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the contract is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a contract for a specific doctor.
     */
    public function forDoctor(Doctor $doctor): static
    {
        return $this->state(fn (array $attributes) => [
            'doctor_id' => $doctor->id,
        ]);
    }

    /**
     * Create a contract with current dates.
     */
    public function current(): static
    {
        return $this->state(fn (array $attributes) => [
            'fecha_inicio' => now()->subMonths(1)->format('Y-m-d'),
            'fecha_fin' => now()->addMonths(11)->format('Y-m-d'),
            'is_active' => true,
        ]);
    }

    /**
     * Create an expired contract.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'fecha_inicio' => now()->subMonths(12)->format('Y-m-d'),
            'fecha_fin' => now()->subMonths(1)->format('Y-m-d'),
            'is_active' => false,
        ]);
    }
}
