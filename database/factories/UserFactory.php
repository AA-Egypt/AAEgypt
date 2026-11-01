<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->userName().'@aaegypt.org',
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('AaEgypt#Service2026!Valid'),
            'role' => 'gsr',
            'group_id' => null,
            'must_change_password' => false,
            'is_suspended' => false,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function webAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'web_admin',
            'group_id' => null,
        ]);
    }

    public function gsr(?int $groupId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'gsr',
            'group_id' => $groupId,
        ]);
    }

    public function delegate(?int $groupId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'delegate',
            'group_id' => $groupId,
        ]);
    }

    public function servicePosition(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'service_position',
            'group_id' => null,
        ]);
    }
}
