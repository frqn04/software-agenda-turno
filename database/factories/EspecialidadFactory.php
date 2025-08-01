<?php

namespace Database\Factories;

use App\Models\Especialidad;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Especialidad>
 */
class EspecialidadFactory extends Factory
{
    protected $model = Especialidad::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => $this->faker->randomElement([
                'Cardiología',
                'Dermatología', 
                'Gastroenterología',
                'Ginecología',
                'Neurología',
                'Oftalmología',
                'Pediatría',
                'Psiquiatría',
                'Traumatología',
                'Urología'
            ]),
            'descripcion' => $this->faker->paragraph(2),
            'activo' => $this->faker->boolean(90), // 90% chance de estar activo
        ];
    }

    /**
     * Indicate that the especialidad is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'activo' => true,
        ]);
    }

    /**
     * Indicate that the especialidad is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'activo' => false,
        ]);
    }
}
