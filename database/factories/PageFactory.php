<?php

namespace Database\Factories;

use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    protected $model = Page::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $titleEn = fake()->sentence(3);

        return [
            'title_ar' => 'صفحة '.fake()->word(),
            'title_en' => $titleEn,
            'slug' => Str::slug($titleEn),
            'body_ar' => '<p>محتوى تجريبي باللغة العربية.</p>',
            'body_en' => '<p>Sample content in English.</p>',
            'is_published' => true,
            'meta_description_ar' => 'وصف الصفحة بالعربية',
            'meta_description_en' => 'Meta description in English',
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => false,
        ]);
    }
}
