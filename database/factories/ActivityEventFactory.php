<?php

namespace Database\Factories;

use App\Models\ActivityEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityEvent>
 */
class ActivityEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['discover', 'swipes.store', 'connections.index', 'messages.store', 'me.show']),
            'category' => ActivityEvent::CATEGORY_API,
            'properties' => ['method' => 'GET'],
            'platform' => fake()->randomElement(['ios', 'android']),
            'app_version' => '1.0.0',
            'status' => 200,
            'duration_ms' => fake()->numberBetween(20, 400),
            'occurred_at' => now(),
        ];
    }

    public function named(string $name): static
    {
        return $this->state(fn () => ['name' => $name]);
    }

    public function at(\DateTimeInterface $when): static
    {
        return $this->state(fn () => ['occurred_at' => $when]);
    }
}
