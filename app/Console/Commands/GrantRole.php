<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Asigna un rol (user, moderator, admin). Los roles nunca se asignan desde la API.
 */
#[Signature('datecito:grant-role {email} {role=moderator}')]
#[Description('Asigna un rol a una cuenta existente')]
class GrantRole extends Command
{
    public function handle(): int
    {
        $role = UserRole::tryFrom((string) $this->argument('role'));

        if ($role === null) {
            $this->components->error('Rol inválido. Usa: user, moderator o admin.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', mb_strtolower((string) $this->argument('email')))->first();

        if ($user === null) {
            $this->components->error('No existe una cuenta con ese correo.');

            return self::FAILURE;
        }

        $user->forceFill(['role' => $role])->save();
        $this->components->info("Rol [{$role->value}] asignado a {$user->email}.");

        return self::SUCCESS;
    }
}
