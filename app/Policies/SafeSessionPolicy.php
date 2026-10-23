<?php

namespace App\Policies;

use App\Models\SafeSession;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SafeSessionPolicy
{
    public function update(User $user, SafeSession $session): Response
    {
        return $session->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}
