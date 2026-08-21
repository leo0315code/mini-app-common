<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'creator_id' => User::factory(),
            'title' => fake()->sentence(3),
            'body' => fake()->paragraph(),
            'type' => fake()->randomElement(['system', 'activity', 'version']),
            'scope' => 'all',
            'targets' => null,
            'published' => true,
            'published_at' => now(),
        ];
    }
}
