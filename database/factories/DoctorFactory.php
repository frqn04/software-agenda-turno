<?php

namespace Database\Factories;

use App\Models\Doctor;
use App\Models\Especialidad;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Doctor>
 */
class DoctorFactory extends Factory
{
    protected $model = Doctor::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => $this->faker->firstName(),
            'apellido' => $this->faker->lastName(),
            'matricula' => $this->faker->unique()->bothify('MP####'),
            'telefono' => $this->faker->phoneNumber(),
            'email' => $this->faker->unique()->safeEmail(),
            'especialidad_id' => Especialidad::factory(),
            'activo' => $this->faker->boolean(85), // 85% chance de estar activo
        ];
    }

    /**
     * Indicate that the doctor is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'activo' => true,
        ]);
    }

    /**
     * Indicate that the doctor is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'activo' => false,
        ]);
    }

    /**
     * Create a doctor with a specific especialidad.
     */
    public function forEspecialidad(Especialidad $especialidad): static
    {
        return $this->state(fn (array $attributes) => [
            'especialidad_id' => $especialidad->id,
        ]);
    }
}
