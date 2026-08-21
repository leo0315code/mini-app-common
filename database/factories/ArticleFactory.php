<?php

namespace Database\Factories;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    protected $model = Article::class;

    public function definition(): array
    {
        return [
            'category_id' => null,
            'title' => fake()->sentence(6),
            'slug' => null,
            'cover' => null,
            'summary' => fake()->sentence(12),
            'content' => fake()->paragraphs(3, true),
            'status' => Article::STATUS_PUBLISHED,
            'is_top' => false,
            'views' => fake()->numberBetween(0, 500),
            'created_by' => User::factory(),
            'published_at' => now()->subDay(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => Article::STATUS_DRAFT,
            'published_at' => null,
        ]);
    }

    public function offline(): static
    {
        return $this->state(fn () => [
            'status' => Article::STATUS_OFFLINE,
        ]);
    }

    public function withCategory(): static
    {
        return $this->state(fn () => [
            'category_id' => Category::factory(),
        ]);
    }
}
