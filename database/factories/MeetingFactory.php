<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\Meeting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Meeting>
 */
class MeetingFactory extends Factory
{
    protected $model = Meeting::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->sentence(3);
        $venue = fake()->streetAddress();

        return [
            'group_id' => Group::factory(),
            'name' => $name,
            'title' => $name,
            'day_of_week' => fake()->numberBetween(0, 6),
            'start_time' => '19:00',
            'end_time' => '20:30',
            'time' => '19:00',
            'venue_address' => $venue,
            'venue' => $venue,
            'city' => fake()->randomElement(['Cairo', 'Alexandria', 'Giza', 'Maadi', 'Zamalek']),
            'latitude' => 30.0444281,
            'longitude' => 31.2357116,
            'type' => 'in_person',
            'online_link' => null,
            'language' => 'ar',
            'notes' => fake()->sentence(6),
            'is_active' => true,
            'version' => 1,
        ];
    }
}
