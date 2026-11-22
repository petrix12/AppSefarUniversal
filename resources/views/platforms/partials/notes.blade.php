<div class="mt-3"><h4 class="h6">Notas de Kalendia</h4>
@foreach($notes as $note)@if(is_string($note['id']??null) && preg_match('/^nt_[A-Za-z0-9_-]+$/D',$note['id']))
    <details class="mb-3"><summary>{{ \Illuminate\Support\Str::limit($note['text']??'',90) }}</summary>
        <form method="POST" action="{{ route('admin.platforms.operate',[$provider,$account,$record]) }}" data-platform-form class="mt-2">@csrf<input type="hidden" name="operation_id" value="{{ \Illuminate\Support\Str::uuid() }}"><input type="hidden" name="action" value="note_update"><input type="hidden" name="note_id" value="{{ $note['id'] }}">
            <label for="note-{{ $loop->index }}">Contenido</label><textarea class="form-control mb-2" id="note-{{ $loop->index }}" name="text" required maxlength="20000">{{ $note['text']??'' }}</textarea>
            <input type="hidden" name="is_pinned" value="0"><label class="d-block"><input type="checkbox" name="is_pinned" value="1" @checked($note['is_pinned']??false)> Fijar nota</label>
            <label class="d-block"><input type="checkbox" name="confirmed" value="1" required> Confirmo esta modificación.</label><button class="btn btn-primary" type="submit">Guardar nota</button>
        </form>
    </details>
@endif @endforeach
<form method="POST" action="{{ route('admin.platforms.operate',[$provider,$account,$record]) }}" data-platform-form>@csrf<input type="hidden" name="operation_id" value="{{ \Illuminate\Support\Str::uuid() }}"><input type="hidden" name="action" value="note_create">
    <label for="new-customer-note">Nueva nota</label><textarea class="form-control mb-2" id="new-customer-note" name="text" required maxlength="20000"></textarea>
    <input type="hidden" name="is_pinned" value="0"><label class="d-block"><input type="checkbox" name="is_pinned" value="1"> Fijar nota</label>
    <label class="d-block"><input type="checkbox" name="confirmed" value="1" required> Confirmo el registro de esta nota en Kalendia.</label><button class="btn btn-primary" type="submit">Agregar nota</button>
</form></div>
