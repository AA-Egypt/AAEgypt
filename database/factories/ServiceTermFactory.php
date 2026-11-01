<?php

namespace Database\Factories;

use App\Models\ServiceTerm;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceTerm>
 */
class ServiceTermFactory extends Factory
{
    protected $model = ServiceTerm::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = Carbon::today()->subMonths(3);
        $endDate = (clone $startDate)->addMonths(6);

        return [
            'user_id' => User::factory()->servicePosition(),
            'position_title' => fake()->jobTitle(),
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'extended_times' => 0,
            'last_extended_by' => null,
        ];
    }

    /**
     * Term expiring in a specific number of days.
     */
    public function expiringInDays(int $days): static
    {
        return $this->state(fn (array $attributes) => [
            'end_date' => Carbon::today()->addDays($days)->toDateString(),
        ]);
    }

    /**
     * Term expiring in exactly 30 days.
     */
    public function expiringIn30Days(): static
    {
        return $this->expiringInDays(30);
    }

    /**
     * Term expiring in exactly 7 days.
     */
    public function expiringIn7Days(): static
    {
        return $this->expiringInDays(7);
    }

    /**
     * Expired term (default yesterday or today).
     */
    public function expired(int $daysAgo = 0): static
    {
        return $this->state(fn (array $attributes) => [
            'end_date' => Carbon::today()->subDays($daysAgo)->toDateString(),
        ]);
    }

    /**
     * Standard term duration in months (3, 6, 9, 12).
     */
    public function standard(int $months): static
    {
        return $this->state(function (array $attributes) use ($months) {
            $startDate = Carbon::today();

            return [
                'start_date' => $startDate->toDateString(),
                'end_date' => (clone $startDate)->addMonths($months)->toDateString(),
            ];
        });
    }

    /**
     * Term marked as extended.
     */
    public function extended(int $times = 1, ?int $byUserId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'extended_times' => $times,
            'last_extended_by' => $byUserId,
        ]);
    }
}
