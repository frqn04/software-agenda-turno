<?php

namespace Database\Factories;

use App\Models\DoctorScheduleSlot;
use App\Models\Doctor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DoctorScheduleSlot>
 */
class DoctorScheduleSlotFactory extends Factory
{
    protected $model = DoctorScheduleSlot::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startHour = $this->faker->numberBetween(8, 17);
        $endHour = $startHour + $this->faker->numberBetween(2, 4);
        
        return [
            'doctor_id' => Doctor::factory(),
            'day_of_week' => $this->faker->numberBetween(1, 5), // Lunes a Viernes
            'start_time' => sprintf('%02d:00:00', $startHour),
            'end_time' => sprintf('%02d:00:00', $endHour),
            'slot_duration_minutes' => $this->faker->randomElement([30, 45, 60]),
            'is_active' => $this->faker->boolean(95), // 95% chance de estar activo
        ];
    }

    /**
     * Indicate that the schedule slot is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the schedule slot is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a schedule slot for a specific doctor.
     */
    public function forDoctor(Doctor $doctor): static
    {
        return $this->state(fn (array $attributes) => [
            'doctor_id' => $doctor->id,
        ]);
    }

    /**
     * Create a morning schedule (8 AM - 12 PM).
     */
    public function morning(): static
    {
        return $this->state(fn (array $attributes) => [
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
        ]);
    }

    /**
     * Create an afternoon schedule (2 PM - 6 PM).
     */
    public function afternoon(): static
    {
        return $this->state(fn (array $attributes) => [
            'start_time' => '14:00:00',
            'end_time' => '18:00:00',
        ]);
    }

    /**
     * Create a schedule for a specific day of the week.
     */
    public function onDay(int $dayOfWeek): static
    {
        return $this->state(fn (array $attributes) => [
            'day_of_week' => $dayOfWeek,
        ]);
    }

    /**
     * Create a schedule for Monday to Friday.
     */
    public function weekdays(): static
    {
        return $this->state(fn (array $attributes) => [
            'day_of_week' => $this->faker->numberBetween(1, 5),
        ]);
    }

    /**
     * Create a schedule with specific duration.
     */
    public function withDuration(int $minutes): static
    {
        return $this->state(fn (array $attributes) => [
            'slot_duration_minutes' => $minutes,
        ]);
    }
}
