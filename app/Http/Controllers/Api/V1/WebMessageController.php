<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWebMessageRequest;
use App\Mail\WebMessageMail;
use App\Models\WebMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Recibe los formularios del sitio web (sin sesión). Guarda el mensaje para el CRM
 * y avisa por correo al equipo correspondiente.
 */
class WebMessageController extends Controller
{
    public function store(StoreWebMessageRequest $request): JsonResponse
    {
        $data = $request->safe()->except(['attachment', 'accepted', 'website']);

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $key = 'web/arco/'.Str::ulid().'.'.strtolower($file->getClientOriginalExtension());
            Storage::disk(config('datecito.photos.disk'))->put($key, (string) file_get_contents($file->getRealPath()));
            $data['attachment_key'] = $key;
        }

        $message = WebMessage::query()->create([
            ...$data,
            'ip_hash' => hash('sha256', (string) $request->ip().config('app.key')),
        ]);

        $notify = config('datecito.web.notify.'.$message->kind);
        if ($notify) {
            Mail::to($notify)->queue(new WebMessageMail($message));
        }

        return response()->json(['folio' => $message->folio()], 201);
    }
}
