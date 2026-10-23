<?php

namespace App\Services;

use App\Exceptions\DomainRuleException;
use App\Models\Block;
use App\Models\Report;
use App\Models\TrustedContact;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Bloqueos, reportes y contactos de confianza.
 *
 * Estas funciones nunca dependen de la membresía: la seguridad no se cobra.
 */
class SafetyService
{
    public function __construct(private ConnectionService $connections) {}

    public function block(User $blocker, User $blocked): void
    {
        if ($blocker->is($blocked)) {
            throw new DomainRuleException('cannot_block_self', 'No puedes bloquearte a ti.');
        }

        DB::transaction(function () use ($blocker, $blocked): void {
            $blocker->blocksMade()->firstOrCreate(['blocked_id' => $blocked->id]);
            $this->connections->endBetween($blocker, $blocked);
        });
    }

    public function unblock(User $blocker, User $blocked): void
    {
        Block::query()->where('blocker_id', $blocker->id)->where('blocked_id', $blocked->id)->delete();
    }

    public function report(User $reporter, ?User $reported, string $reason, ?string $details, bool $alsoBlock): Report
    {
        return DB::transaction(function () use ($reporter, $reported, $reason, $details, $alsoBlock): Report {
            $report = new Report(['reported_id' => $reported?->id, 'reason' => $reason, 'details' => $details]);
            $report->forceFill(['reporter_id' => $reporter->id])->save();

            if ($alsoBlock && $reported !== null) {
                $this->block($reporter, $reported);
            }

            return $report;
        });
    }

    public function addContact(User $user, string $name, string $phone): TrustedContact
    {
        return DB::transaction(function () use ($user, $name, $phone): TrustedContact {
            $count = TrustedContact::query()->where('user_id', $user->id)->lockForUpdate()->count();

            if ($count >= config('datecito.limits.trusted_contacts_max')) {
                throw new DomainRuleException('contacts_limit', 'Puedes tener hasta '.config('datecito.limits.trusted_contacts_max').' contactos de confianza.');
            }

            return $user->trustedContacts()->create(['name' => $name, 'phone' => $phone]);
        });
    }
}
