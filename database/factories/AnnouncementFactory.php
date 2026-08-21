<?php

namespace Database\Factories;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    protected $model = Announcement::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(6),
            'content' => fake()->paragraphs(2, true),
            'type' => fake()->randomElement([
                Announcement::TYPE_NOTICE,
                Announcement::TYPE_ACTIVITY,
                Announcement::TYPE_UPDATE,
            ]),
            'status' => Announcement::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'created_by' => User::factory(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => Announcement::STATUS_DRAFT,
            'published_at' => null,
        ]);
    }

    public function offline(): static
    {
        return $this->state(fn () => [
            'status' => Announcement::STATUS_OFFLINE,
        ]);
    }
}
