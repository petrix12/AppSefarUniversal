@extends('adminlte::page')
@section('title', 'BrightBean')
@section('content_header')
    <h1>Mensajes de BrightBean</h1>
@stop
@section('content')
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif
    <div class="card">
        <div class="card-body">
            <p>Última sincronización: {{ $settings->last_synced_at?->format('d/m/Y H:i:s') ?? 'Pendiente de conectar' }}</p>
            <p><a href="https://chat.sefaruniversal.com" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary">Abrir bandeja de BrightBean</a>
                <a href="{{ route('admin.integrations.ai-models.index') }}" class="btn btn-link">Modelos de IA</a></p>
            <form method="POST" action="{{ route('admin.integrations.brightbean.update') }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="ai_enabled" value="0">
                <label><input type="checkbox" name="ai_enabled" value="1" @checked(old('ai_enabled', $settings->ai_enabled))> Responder automáticamente con IA</label>
                <p class="text-muted">Las respuestas automáticas respetan las pausas de los agentes y las reglas del canal. Los adjuntos se sincronizan; la IA responde a mensajes de texto.</p>
                <div class="form-group">
                    <label for="brightbean-instructions">Indicaciones de atención al cliente</label>
                    <textarea id="brightbean-instructions" name="instructions" rows="4" class="form-control" maxlength="10000">{{ old('instructions', $settings->instructions) }}</textarea>
                </div>
                <button class="btn btn-primary" type="submit">Guardar</button>
            </form>
        </div>
    </div>
    @if($conversation)
        <p><a href="{{ route('admin.integrations.brightbean.index') }}">Ver todos los mensajes</a></p>
    @endif
    <div class="card"><div class="card-body">
        @forelse($messages as $message)
            <article class="border-bottom mb-3 pb-3">
                <div><strong>{{ trim(($message->contact['first_name'] ?? '').' '.($message->contact['last_name'] ?? '')) ?: ($message->contact['phone'] ?? 'Contacto') }}</strong>
                    · {{ $message->platform }} · {{ $message->direction === 'in' ? 'Recibido' : 'Enviado' }} · {{ $message->status }}
                    @if($message->internal) · Nota interna @endif
                    <span class="text-muted"> · {{ $message->source_created_at->format('d/m/Y H:i:s') }}</span></div>
                <div style="white-space: pre-wrap; overflow-wrap: anywhere">{{ $message->status === 'deleted' ? 'Mensaje eliminado' : ($message->text ?: 'Mensaje con adjuntos u otro contenido') }}</div>
                <a href="{{ route('admin.integrations.brightbean.index', ['conversation' => $message->conversation_id]) }}">Ver conversación</a>
            </article>
        @empty
            <p class="text-muted">Los mensajes aparecerán aquí cuando se conecte un canal y comience la sincronización.</p>
        @endforelse
        {{ $messages->links() }}
    </div></div>
@stop
