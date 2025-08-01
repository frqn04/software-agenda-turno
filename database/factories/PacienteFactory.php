<?php

namespace Database\Factories;

use App\Models\Paciente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Paciente>
 */
class PacienteFactory extends Factory
{
    protected $model = Paciente::class;

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
            'dni' => $this->faker->unique()->numberBetween(10000000, 99999999),
            'fecha_nacimiento' => $this->faker->date('Y-m-d', '-18 years'),
            'telefono' => $this->faker->phoneNumber(),
            'email' => $this->faker->optional()->safeEmail(),
            'direccion' => $this->faker->optional()->address(),
            'obra_social' => $this->faker->optional()->randomElement([
                'OSDE', 'Swiss Medical', 'Galeno', 'Medicus', 'Sancor Salud', 'IOMA'
            ]),
            'numero_afiliado' => $this->faker->optional()->bothify('########'),
            'activo' => $this->faker->boolean(95), // 95% chance de estar activo
        ];
    }

    /**
     * Indicate that the paciente is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'activo' => true,
        ]);
    }

    /**
     * Indicate that the paciente is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'activo' => false,
        ]);
    }

    /**
     * Create a paciente with obra social.
     */
    public function withObraSocial(): static
    {
        return $this->state(fn (array $attributes) => [
            'obra_social' => $this->faker->randomElement([
                'OSDE', 'Swiss Medical', 'Galeno', 'Medicus', 'Sancor Salud', 'IOMA'
            ]),
            'numero_afiliado' => $this->faker->bothify('########'),
        ]);
    }

    /**
     * Create a paciente without obra social.
     */
    public function withoutObraSocial(): static
    {
        return $this->state(fn (array $attributes) => [
            'obra_social' => null,
            'numero_afiliado' => null,
        ]);
    }
}
