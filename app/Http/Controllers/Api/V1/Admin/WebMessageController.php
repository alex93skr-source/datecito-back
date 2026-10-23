<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\WebMessageResource;
use App\Models\WebMessage;
use App\Services\Admin\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Bandeja de mensajes del sitio web (contacto, alianzas, ARCO, boletín, lista de espera).
 */
class WebMessageController extends Controller
{
    public function __construct(private AuditLog $audit) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'kind' => ['nullable', Rule::in(WebMessage::KINDS)],
            'status' => ['nullable', Rule::in([...WebMessage::STATUSES, 'all'])],
            'q' => ['nullable', 'string', 'max:160'],
        ]);

        $messages = WebMessage::query()
            ->when($request->filled('kind'), fn (Builder $q) => $q->where('kind', $request->string('kind')))
            ->when(
                $request->filled('status'),
                fn (Builder $q) => $request->input('status') === 'all' ? $q : $q->where('status', $request->string('status')),
                fn (Builder $q) => $q->whereIn('status', ['new', 'read']),
            )
            ->when($request->filled('q'), fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('email', 'like', '%'.$request->string('q').'%')
                ->orWhere('name', 'like', '%'.$request->string('q').'%')
                ->orWhere('company', 'like', '%'.$request->string('q').'%')))
            ->with('handler')
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return WebMessageResource::collection($messages);
    }

    public function update(Request $request, WebMessage $message): WebMessageResource
    {
        $request->validate(['status' => ['required', Rule::in(WebMessage::STATUSES)]]);

        $message->forceFill([
            'status' => $request->string('status'),
            'handled_by' => $request->user()->id,
            'handled_at' => now(),
        ])->save();
        $this->audit->record('web_message.'.$request->string('status'), $message, ['kind' => $message->kind]);

        return WebMessageResource::make($message->load('handler'));
    }

    /**
     * Enlace temporal (10 min) a la identificación adjunta de una solicitud ARCO.
     */
    public function attachment(WebMessage $message): array
    {
        abort_if($message->attachment_key === null, 404);

        return ['url' => Storage::disk(config('datecito.photos.signing_disk'))->temporaryUrl($message->attachment_key, now()->addMinutes(10))];
    }
}
