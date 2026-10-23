<?php

namespace Database\Factories;

use App\Models\Connection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Connection>
 */
class ConnectionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source' => 'discover',
        ];
    }

    public function between(User $a, User $b): static
    {
        [$one, $two] = Connection::orderedPair($a->id, $b->id);

        return $this->state(fn () => ['user_one_id' => $one, 'user_two_id' => $two]);
    }
}
