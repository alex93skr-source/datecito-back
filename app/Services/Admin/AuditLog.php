<?php

namespace App\Services\Admin;

use App\Models\AdminAction;
use App\Models\User;
use App\Services\ActivityRecorder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Bitácora de lo que hace el equipo desde el CRM. Cada acción sensible pasa por aquí.
 */
class AuditLog
{
    public function __construct(private Request $request, private ActivityRecorder $activity) {}

    /**
     * @param  array<string, mixed>|null  $changes
     */
    public function record(string $action, Model|string|null $subject = null, ?array $changes = null, ?string $note = null): AdminAction
    {
        [$type, $id] = match (true) {
            $subject instanceof Model => [class_basename($subject), (string) $subject->getKey()],
            is_string($subject) => ['User', $subject],
            default => [null, null],
        };

        return AdminAction::query()->create([
            'actor_id' => $this->request->user()?->id,
            'action' => $action,
            'subject_type' => $type,
            'subject_id' => $id,
            'changes' => $changes ?: null,
            'note' => $note !== null ? mb_substr($note, 0, 500) : null,
            'ip_hash' => $this->activity->ipHash($this->request),
        ]);
    }

    /**
     * Acción sobre una persona: además de la bitácora, deja huella en su línea de tiempo.
     *
     * @param  array<string, mixed>|null  $changes
     */
    public function recordOnUser(string $action, User $user, ?array $changes = null, ?string $note = null): AdminAction
    {
        $this->activity->recordSystem($user, 'admin.'.$action, array_filter(['by' => $this->request->user()?->id, ...($changes ?? [])]));

        return $this->record($action, $user, $changes, $note);
    }
}
