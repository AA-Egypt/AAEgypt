<?php

namespace Database\Factories;

use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    protected $model = Group::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->city().' Group';

        return [
            'name_en' => $name,
            'name_ar' => 'مجموعة '.fake()->city(),
            'slug' => Str::slug($name).'-'.fake()->unique()->randomNumber(5),
        ];
    }
}
