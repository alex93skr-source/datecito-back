<?php

namespace App\Policies;

use App\Models\Datecito;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DatecitoPolicy
{
    public function __construct(private ConnectionPolicy $connections) {}

    public function view(User $user, Datecito $datecito): Response
    {
        return $datecito->connection->involves($user) ? Response::allow() : Response::denyAsNotFound();
    }

    public function update(User $user, Datecito $datecito): Response
    {
        return $this->connections->view($user, $datecito->connection);
    }
}
