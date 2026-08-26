<?php

namespace Database\Factories;

use App\Models\Banner;
use App\Models\Article;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Banner>
 */
class BannerFactory extends Factory
{
    protected $model = Banner::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'image' => 'banners/'.fake()->uuid().'.png',
            'link_type' => Banner::LINK_NONE,
            'article_id' => null,
            'url' => null,
            'sort_order' => fake()->numberBetween(0, 100),
            'starts_at' => null,
            'ends_at' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function future(): static
    {
        return $this->state(fn () => [
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(10),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->subDay(),
        ]);
    }

    public function articleLink(Article $article = null): static
    {
        return $this->state(fn () => [
            'link_type' => Banner::LINK_ARTICLE,
            'article_id' => $article?->id ?? Article::factory(),
            'url' => null,
        ]);
    }

    public function urlLink(): static
    {
        return $this->state(fn () => [
            'link_type' => Banner::LINK_URL,
            'article_id' => null,
            'url' => 'https://example.com/'.fake()->slug(),
        ]);
    }
}
