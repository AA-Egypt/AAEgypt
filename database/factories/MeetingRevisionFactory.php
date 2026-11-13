<?php

namespace Database\Factories;

use App\Models\Meeting;
use App\Models\MeetingRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeetingRevision>
 */
class MeetingRevisionFactory extends Factory
{
    protected $model = MeetingRevision::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'meeting_id' => Meeting::factory(),
            'user_id' => User::factory(),
            'version' => 2,
            'old_payload' => [
                'name' => fake()->sentence(3),
                'day_of_week' => 1,
                'start_time' => '19:00',
                'end_time' => '20:30',
            ],
            'new_payload' => [
                'name' => fake()->sentence(3),
                'day_of_week' => 1,
                'start_time' => '19:30',
                'end_time' => '21:00',
            ],
            'created_at' => now(),
        ];
    }
}
