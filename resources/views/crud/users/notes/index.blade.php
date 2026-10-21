@extends('adminlte::page')

@section('title', 'Notas de ' . $client->name)

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <h1>Notas de {{ $client->name }}</h1>
            <p class="text-muted mb-0">
                {{ $client->email }}
                @if($client->passport)
                    · Pasaporte: {{ $client->passport }}
                @endif
            </p>
        </div>
        <a href="{{ route('crud.users.edit', $client) }}" class="btn btn-outline-secondary mt-2">
            Volver a la ficha del cliente
        </a>
    </div>
@stop

@section('content')
    @if(session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif

    <div class="row">
        <div class="col-lg-4">
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h2 class="card-title">Nueva nota</h2>
                </div>
                <form method="POST" action="{{ route('crud.users.notes.store', $client) }}">
                    @csrf
                    <div class="card-body">
                        <p class="text-muted">Las notas son internas y se guardan con tu nombre y la fecha.</p>
                        <label for="client-note">Contenido de la nota</label>
                        <textarea id="client-note" name="note" rows="8"
                                  class="form-control @error('note') is-invalid @enderror"
                                  maxlength="{{ \App\Models\ClientNote::MAX_LENGTH }}"
                                  placeholder="Escribe una nota sobre este cliente..." required>{{ old('note') }}</textarea>
                        @error('note')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="card-footer text-right">
                        <button type="submit" class="btn btn-primary">Guardar nota</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-8">
            <h2 class="h5 mb-3">Notas del cliente <span class="badge badge-secondary">{{ $notes->total() }}</span></h2>
            @forelse($notes as $note)
                <article class="card">
                    <div class="card-header d-flex justify-content-between flex-wrap">
                        <strong>{{ $note->author?->name ?? 'Usuario eliminado' }}</strong>
                        <time datetime="{{ $note->created_at->toIso8601String() }}" class="text-muted ml-2">
                            {{ $note->created_at->format('d/m/Y H:i') }}
                        </time>
                    </div>
                    <div class="card-body" style="white-space:pre-wrap; overflow-wrap:anywhere;">{{ $note->note }}</div>
                </article>
            @empty
                <div class="card">
                    <div class="card-body text-muted">Aún no hay notas para este cliente.</div>
                </div>
            @endforelse

            {{ $notes->links() }}
        </div>
    </div>
@stop
