<?php

namespace Database\Factories;

use App\Models\Feedback;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Feedback>
 */
class FeedbackFactory extends Factory
{
    protected $model = Feedback::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => fake()->randomElement([
                Feedback::TYPE_SUGGESTION,
                Feedback::TYPE_BUG,
                Feedback::TYPE_COMPLAINT,
                Feedback::TYPE_OTHER,
            ]),
            'content' => fake()->sentence(20),
            'contact' => fake()->optional()->phoneNumber(),
            'status' => Feedback::STATUS_PENDING,
        ];
    }
}
