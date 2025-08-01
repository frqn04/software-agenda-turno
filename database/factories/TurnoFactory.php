<?php

namespace Database\Factories;

use App\Models\Turno;
use App\Models\Doctor;
use App\Models\Paciente;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Turno>
 */
class TurnoFactory extends Factory
{
    protected $model = Turno::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fecha = $this->faker->dateTimeBetween('now', '+30 days');
        $horaInicio = $this->faker->time('H:i:s', '18:00:00');
        
        // Calcular hora fin (30 o 60 minutos después)
        $duracion = $this->faker->randomElement([30, 60]);
        $horaFin = Carbon::createFromFormat('H:i:s', $horaInicio)
            ->addMinutes($duracion)
            ->format('H:i:s');

        return [
            'doctor_id' => Doctor::factory(),
            'paciente_id' => Paciente::factory(),
            'fecha' => $fecha->format('Y-m-d'),
            'hora_inicio' => $horaInicio,
            'hora_fin' => $horaFin,
            'duration_minutes' => $duracion,
            'estado' => $this->faker->randomElement(['programado', 'confirmado', 'completado', 'cancelado']),
            'motivo_consulta' => $this->faker->optional()->sentence(),
            'observaciones' => $this->faker->optional()->paragraph(),
        ];
    }

    /**
     * Indicate that the turno is programmed.
     */
    public function programmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'programado',
        ]);
    }

    /**
     * Indicate that the turno is confirmed.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'confirmado',
        ]);
    }

    /**
     * Indicate that the turno is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'completado',
        ]);
    }

    /**
     * Indicate that the turno is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'cancelado',
        ]);
    }

    /**
     * Create a turno for today.
     */
    public function today(): static
    {
        return $this->state(fn (array $attributes) => [
            'fecha' => today()->format('Y-m-d'),
        ]);
    }

    /**
     * Create a turno for tomorrow.
     */
    public function tomorrow(): static
    {
        return $this->state(fn (array $attributes) => [
            'fecha' => tomorrow()->format('Y-m-d'),
        ]);
    }

    /**
     * Create a turno with specific duration.
     */
    public function withDuration(int $minutes): static
    {
        return $this->state(function (array $attributes) use ($minutes) {
            $horaInicio = $attributes['hora_inicio'] ?? '10:00:00';
            $horaFin = Carbon::createFromFormat('H:i:s', $horaInicio)
                ->addMinutes($minutes)
                ->format('H:i:s');

            return [
                'duration_minutes' => $minutes,
                'hora_fin' => $horaFin,
            ];
        });
    }

    /**
     * Create a turno at specific time.
     */
    public function atTime(string $hora): static
    {
        return $this->state(function (array $attributes) use ($hora) {
            $duracion = $attributes['duration_minutes'] ?? 30;
            $horaFin = Carbon::createFromFormat('H:i:s', $hora)
                ->addMinutes($duracion)
                ->format('H:i:s');

            return [
                'hora_inicio' => $hora,
                'hora_fin' => $horaFin,
            ];
        });
    }
}
