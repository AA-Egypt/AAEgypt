<?php

namespace Database\Factories;

use App\Models\DmsDocument;
use App\Models\DmsFolder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DmsDocument>
 */
class DmsDocumentFactory extends Factory
{
    protected $model = DmsDocument::class;

    public function definition(): array
    {
        $filename = fake()->word().'.pdf';

        return [
            'folder_id' => DmsFolder::factory(),
            'user_id' => User::factory(),
            'original_name' => $filename,
            'sanitized_name' => Str::slug(pathinfo($filename, PATHINFO_FILENAME)).'.pdf',
            'file_path' => '1/'.fake()->uuid().'_file.pdf',
            'file_size' => fake()->numberBetween(1024, 10485760),
            'mime_type' => 'application/pdf',
            'sha256_hash' => hash('sha256', fake()->text()),
            'version' => 1,
            'status' => 'clean',
        ];
    }
}
