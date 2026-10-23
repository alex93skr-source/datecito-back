<?php

namespace App\Policies;

use App\Models\Connection;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Sólo las dos personas de una conexión activa, sin bloqueos, pueden verla o escribir.
 * A cualquier otra persona se le responde 404 para no revelar que existe.
 */
class ConnectionPolicy
{
    public function view(User $user, Connection $connection): Response
    {
        return $connection->involves($user)
            && $connection->isActive()
            && ! $user->hasBlockWith($connection->other($user))
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function message(User $user, Connection $connection): Response
    {
        return $this->view($user, $connection);
    }

    public function end(User $user, Connection $connection): Response
    {
        return $connection->involves($user) ? Response::allow() : Response::denyAsNotFound();
    }
}
