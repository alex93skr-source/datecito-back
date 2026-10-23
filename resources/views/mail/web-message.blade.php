Folio: {{ $m->folio() }}
Tipo: {{ $m->kind }} · Idioma: {{ $m->lang }} · Recibido: {{ $m->created_at->timezone(config('datecito.timezone'))->format('d/m/Y H:i') }}

Nombre: {{ $m->name ?? '—' }}
Correo: {{ $m->email }}
@if($m->topic)
Tema / derecho: {{ $m->topic }}
@endif
@if($m->company)
Empresa: {{ $m->company }}
@endif
@if($m->city)
Ciudad: {{ $m->city }}
@endif
@if($m->attachment_key)
Adjunto: sí (ver en el CRM)
@endif

@if($m->message)
Mensaje:
{{ $m->message }}
@endif

Responde directamente a este correo para contestarle a la persona.
Gestiona el mensaje en el CRM: {{ config('datecito.crm.url') }}/mensajes
