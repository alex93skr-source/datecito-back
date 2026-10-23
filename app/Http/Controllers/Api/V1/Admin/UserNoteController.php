<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserNoteRequest;
use App\Http\Resources\Admin\UserNoteResource;
use App\Models\User;
use App\Models\UserNote;
use App\Services\Admin\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Notas internas del equipo sobre una persona.
 */
class UserNoteController extends Controller
{
    public function __construct(private AuditLog $audit) {}

    public function store(StoreUserNoteRequest $request, User $user): UserNoteResource
    {
        $note = new UserNote($request->validated());
        $note->forceFill(['user_id' => $user->id, 'author_id' => $request->user()->id])->save();
        $this->audit->record('note.created', $user, ['note_id' => $note->id]);

        return UserNoteResource::make($note->load('author'));
    }

    public function update(StoreUserNoteRequest $request, UserNote $note): UserNoteResource
    {
        $this->guard($request, $note);
        $note->update($request->validated());

        return UserNoteResource::make($note->load('author'));
    }

    public function destroy(Request $request, UserNote $note): Response
    {
        $this->guard($request, $note);
        $note->delete();
        $this->audit->record('note.deleted', $note->user_id, ['note_id' => $note->id]);

        return response()->noContent();
    }

    /**
     * Sólo quien escribió la nota (o un admin) puede cambiarla o borrarla.
     */
    private function guard(Request $request, UserNote $note): void
    {
        abort_unless($note->author_id === $request->user()->id || $request->user()->isAdmin(), 403);
    }
}
