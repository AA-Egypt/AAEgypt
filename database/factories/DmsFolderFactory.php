<?php

namespace Database\Factories;

use App\Models\DmsFolder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DmsFolder>
 */
class DmsFolderFactory extends Factory
{
    protected $model = DmsFolder::class;

    public function definition(): array
    {
        return [
            'parent_id' => null,
            'name_ar' => 'مجلد '.fake()->word(),
            'name_en' => fake()->words(2, true).' Folder',
            'required_role' => null,
            'group_id' => null,
        ];
    }

    public function treasury(): static
    {
        return $this->state(fn () => [
            'name_ar' => 'تقارير الخزانة',
            'name_en' => 'Treasury Reports',
            'required_role' => 'treasurer',
            'group_id' => null,
        ]);
    }

    public function groupScoped(int $groupId): static
    {
        return $this->state(fn () => [
            'group_id' => $groupId,
        ]);
    }
}
