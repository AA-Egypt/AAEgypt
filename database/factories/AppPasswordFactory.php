<?php

namespace Database\Factories;

use App\Models\AppPassword;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<AppPassword>
 */
class AppPasswordFactory extends Factory
{
    protected $model = AppPassword::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['iPhone Mail', 'MacBook Outlook', 'Thunderbird Desktop', 'iPad Mail']),
            'password_hash' => Hash::make(Str::random(24)),
            'last_used_at' => null,
            'revoked_at' => null,
        ];
    }

    /**
     * Indicate that the app password is revoked.
     */
    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'revoked_at' => now(),
        ]);
    }

    /**
     * Indicate that the app password has been used.
     */
    public function used(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_used_at' => now()->subDay(),
        ]);
    }
}
