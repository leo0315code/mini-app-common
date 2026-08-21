<?php

namespace Database\Factories;

use App\Models\Media;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MediaFactory extends Factory
{
    protected $model = Media::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'collection' => 'default',
            'file_name' => fake()->word() . '.png',
            'path' => 'uploads/default/' . fake()->uuid() . '.png',
            'disk' => 'public',
            'mime_type' => 'image/png',
            'url' => 'https://example.com/storage/uploads/default/test.png',
            'size' => fake()->numberBetween(1024, 102400),
            'meta' => [],
        ];
    }
}
