<?php

namespace App\Services;

use App\Enums\DatecitoStatus;
use App\Exceptions\DomainRuleException;
use App\Models\Connection;
use App\Models\Datecito;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * "De match a datecito": proponer, confirmar, cancelar y contar cómo fue.
 */
class DatecitoService
{
    /**
     * @param  array{kind: string, place: string, zone: string, scheduled_at: string, note?: ?string}  $data
     */
    public function propose(Connection $connection, User $proposer, array $data): Datecito
    {
        $at = CarbonImmutable::parse($data['scheduled_at'])->setTimezone(config('datecito.timezone'));

        return $connection->datecitos()->create([
            ...Arr::except($data, 'scheduled_at'),
            'proposer_id' => $proposer->id,
            'scheduled_at' => $at->utc(),
            'scheduled_on' => $at->toDateString(),
            'when_label' => self::label($at),
        ]);
    }

    /**
     * "Sábado 18 de octubre, 6:00 p. m." en la zona horaria de Datecito.
     */
    public static function label(CarbonImmutable $at): string
    {
        return Str::ucfirst($at->locale('es')->translatedFormat('l j \d\e F, g:i a'));
    }

    public function confirm(Datecito $datecito, User $user): Datecito
    {
        if ($datecito->proposer_id === $user->id) {
            throw new DomainRuleException('cannot_confirm_own', 'La otra persona debe confirmar tu propuesta.', 403);
        }

        if ($datecito->status !== DatecitoStatus::Proposed) {
            throw new DomainRuleException('datecito_not_pending', 'Este datecito ya no está pendiente.');
        }

        $datecito->forceFill(['status' => DatecitoStatus::Confirmed, 'confirmed_at' => now()])->save();

        return $datecito;
    }

    public function cancel(Datecito $datecito): Datecito
    {
        if ($datecito->status === DatecitoStatus::Cancelled) {
            return $datecito;
        }

        $datecito->forceFill(['status' => DatecitoStatus::Cancelled, 'cancelled_at' => now()])->save();

        return $datecito;
    }

    /**
     * @param  array<string, string>  $answers
     */
    public function leaveFeedback(Datecito $datecito, User $user, array $answers): void
    {
        if ($datecito->status !== DatecitoStatus::Confirmed) {
            throw new DomainRuleException('datecito_not_confirmed', 'Sólo puedes calificar datecitos confirmados.');
        }

        if ($datecito->feedback()->where('user_id', $user->id)->exists()) {
            throw new DomainRuleException('feedback_already_sent', 'Ya nos contaste cómo te fue.');
        }

        $datecito->feedback()->create(['user_id' => $user->id, 'answers' => $answers]);
    }
}
