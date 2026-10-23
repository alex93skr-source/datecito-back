<?php

namespace App\Policies;

use App\Models\TrustedContact;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TrustedContactPolicy
{
    public function delete(User $user, TrustedContact $contact): Response
    {
        return $contact->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}
