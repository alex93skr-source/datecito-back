<?php

namespace App\Services;

use App\Enums\DatecitoStatus;
use App\Enums\SafeSessionStatus;
use App\Exceptions\DomainRuleException;
use App\Models\Datecito;
use App\Models\SafeSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Datecito Seguro: contacto de confianza, check-in y botón de ayuda.
 *
 * Los avisos al contacto se envían desde el celular (WhatsApp/SMS del propio
 * usuario) para no depender de proveedores de pago. El servidor guarda el
 * estado y prepara el texto del mensaje.
 */
class SafeSessionService
{
    public function current(User $user): ?SafeSession
    {
        return SafeSession::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [SafeSessionStatus::Active, SafeSessionStatus::Help, SafeSessionStatus::Overdue])
            ->latest()
            ->first();
    }

    /**
     * @param  list<string>  $contactIds
     */
    public function start(User $user, Datecito $datecito, array $contactIds, int $checkInMinutes, bool $shareLocation): SafeSession
    {
        if ($datecito->status === DatecitoStatus::Cancelled) {
            throw new DomainRuleException('datecito_cancelled', 'Este datecito fue cancelado.');
        }

        $ownedContacts = $user->trustedContacts()->whereKey($contactIds)->count();
        if ($ownedContacts !== count(array_unique($contactIds))) {
            throw new DomainRuleException('contacts_invalid', 'Elige contactos de confianza válidos.');
        }

        return DB::transaction(function () use ($user, $datecito, $contactIds, $checkInMinutes, $shareLocation): SafeSession {
            $this->current($user)?->forceFill(['status' => SafeSessionStatus::Ended, 'ended_at' => now()])->save();

            $session = new SafeSession([
                'datecito_id' => $datecito->id,
                'contact_ids' => array_values(array_unique($contactIds)),
                'check_in_at' => now()->addMinutes($checkInMinutes),
                'check_in_minutes' => $checkInMinutes,
                'share_location' => $shareLocation,
            ]);
            $session->forceFill(['user_id' => $user->id, 'status' => SafeSessionStatus::Active])->save();

            return $session;
        });
    }

    public function checkIn(SafeSession $session): SafeSession
    {
        // Se vuelve a preguntar con el mismo intervalo que eligió la persona.
        $session->forceFill([
            'status' => SafeSessionStatus::Active,
            'check_in_at' => now()->addMinutes($session->check_in_minutes),
        ])->save();

        return $session;
    }

    public function requestHelp(SafeSession $session): SafeSession
    {
        $session->forceFill(['status' => SafeSessionStatus::Help, 'help_requested_at' => now()])->save();

        return $session;
    }

    public function end(SafeSession $session): SafeSession
    {
        $session->forceFill(['status' => SafeSessionStatus::Ended, 'ended_at' => now()])->save();

        return $session;
    }

    /**
     * Marca como vencidas las sesiones sin check-in (lo ejecuta el scheduler).
     */
    public function markOverdue(): int
    {
        return SafeSession::query()
            ->where('status', SafeSessionStatus::Active)
            ->where('check_in_at', '<', now()->subMinutes(10))
            ->update(['status' => SafeSessionStatus::Overdue]);
    }

    /**
     * Texto que el celular enviará al contacto de confianza.
     */
    public function shareMessage(SafeSession $session, User $user): string
    {
        $datecito = $session->datecito;
        $other = $datecito->connection->other($user);
        $text = sprintf(
            '%s tiene un datecito con %s en %s (%s), %s. Si no responde a su check-in te avisaremos.',
            $user->name,
            $other->name,
            $datecito->place,
            $datecito->zone,
            mb_strtolower($datecito->when_label),
        );

        if ($session->status === SafeSessionStatus::Help) {
            $text = sprintf('%s pidió ayuda desde Datecito durante su datecito en %s (%s). Contáctale ahora.', $user->name, $datecito->place, $datecito->zone);
        }

        return $text;
    }
}
