<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'action' => 'update',
            'resource' => 'Meeting',
            'resource_id' => (string) fake()->randomNumber(3),
            'payload' => ['title' => fake()->sentence()],
            'ip_address' => fake()->ipv4(),
            'created_at' => now(),
        ];
    }
}
