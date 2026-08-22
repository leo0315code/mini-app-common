<?php

namespace Database\Factories;

use App\Models\SubscribeMessageFailure;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscribeMessageFailureFactory extends Factory
{
    protected $model = SubscribeMessageFailure::class;

    public function definition(): array
    {
        return [
            'job_uuid' => $this->faker->uuid(),
            'scene' => $this->faker->randomElement(['feedback_handled', 'announcement_published', 'notification_published', 'direct']),
            'subject_type' => null,
            'subject_id' => null,
            'openid' => 'oTEST_' . strtoupper(substr(md5(uniqid()), 0, 16)),
            'template_id' => 'TPL_' . strtoupper(substr(md5(uniqid()), 0, 20)),
            'payload' => [
                'data' => [
                    'thing1' => ['value' => $this->faker->sentence(4)],
                    'time2' => ['value' => $this->faker->dateTimeThisMonth->format('Y-m-d H:i')],
                ],
                'page' => 'pages/index/index',
                'options' => [],
            ],
            'page' => 'pages/index/index',
            'attempts' => $this->faker->numberBetween(1, 5),
            'last_errcode' => $this->faker->randomElement([43101, 40037, -1, -999, 40003]),
            'last_errmsg' => $this->faker->sentence(),
            'last_attempted_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'resolved_at' => null,
            'resolved_note' => null,
        ];
    }
}
