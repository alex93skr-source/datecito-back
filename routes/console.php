<?php

use App\Models\EmailCode;
use App\Services\HereService;
use App\Services\SafeSessionService;
use Illuminate\Support\Facades\Schedule;

/*
| Tareas programadas (las ejecuta el contenedor "scheduler").
*/

Schedule::call(fn (SafeSessionService $sessions) => $sessions->markOverdue())
    ->name('safe-sessions:overdue')
    ->everyMinute()
    ->withoutOverlapping();

// Aquí y ahora no guarda historial: las sesiones vencidas se borran.
Schedule::call(fn (HereService $here) => $here->purgeExpired())
    ->name('here:purge')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::call(fn () => EmailCode::query()->where('expires_at', '<', now()->subDay())->delete())
    ->name('email-codes:prune')
    ->daily();

Schedule::command('sanctum:prune-expired --hours=24')->daily();
