<?php

namespace App\Services;

use App\Enums\SwipeType;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Ritmo diario de Descubrir: cuántos likes y cuántos perfiles por día.
 *
 * Con pocas personas por zona, esto evita que alguien "se acabe" la app en una
 * tarde y le da intención a cada like. Se reinicia a medianoche (datecito.timezone).
 */
class DailyPacingService
{
    public function dayStart(): CarbonImmutable
    {
        return CarbonImmutable::now(config('datecito.timezone'))->startOfDay()->utc();
    }

    public function resetsAt(): CarbonImmutable
    {
        return $this->dayStart()->addDay();
    }

    public function likesLeft(User $user): int
    {
        $used = $user->swipes()->where('type', SwipeType::Like)->where('created_at', '>=', $this->dayStart())->count();

        return max(0, (int) config('datecito.limits.likes_per_day') - $used);
    }

    public function profilesLeft(User $user): int
    {
        $seen = $user->swipes()->where('created_at', '>=', $this->dayStart())->count();

        return max(0, (int) config('datecito.limits.profiles_per_day') - $seen);
    }

    /**
     * @return array{likes_left: int, likes_per_day: int, profiles_left: int, profiles_per_day: int, resets_at: string}
     */
    public function summary(User $user): array
    {
        return [
            'likes_left' => $this->likesLeft($user),
            'likes_per_day' => (int) config('datecito.limits.likes_per_day'),
            'profiles_left' => $this->profilesLeft($user),
            'profiles_per_day' => (int) config('datecito.limits.profiles_per_day'),
            'resets_at' => $this->resetsAt()->toIso8601String(),
        ];
    }
}
