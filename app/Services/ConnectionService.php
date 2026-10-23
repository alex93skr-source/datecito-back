<?php

namespace App\Services;

use App\Enums\ConnectionSource;
use App\Models\Connection;
use App\Models\User;

/**
 * Crea y termina conexiones (matches).
 */
class ConnectionService
{
    public function __construct(private ActivityRecorder $activity) {}

    public function open(User $a, User $b, ConnectionSource $source): Connection
    {
        [$one, $two] = Connection::orderedPair($a->id, $b->id);

        $connection = Connection::query()->firstOrCreate(
            ['user_one_id' => $one, 'user_two_id' => $two],
            ['source' => $source],
        );

        if ($connection->wasRecentlyCreated) {
            foreach ([$a, $b] as $user) {
                $this->activity->recordSystem($user, 'connection.created', ['source' => $source->value]);
            }
        }

        return $connection;
    }

    /**
     * "Quitar match": la conversación deja de estar disponible para ambas personas.
     */
    public function end(Connection $connection, User $by): void
    {
        if ($connection->ended_at !== null) {
            return;
        }

        $connection->forceFill(['ended_at' => now(), 'ended_by' => $by->id])->save();
    }

    public function endBetween(User $a, User $b): void
    {
        $connection = Connection::between($a, $b);

        if ($connection !== null) {
            $this->end($connection, $a);
        }
    }
}
