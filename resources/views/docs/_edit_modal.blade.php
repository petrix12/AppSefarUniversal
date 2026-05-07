<div class="modal fade" id="editDocument{{ $doc->id }}" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form method="POST" action="{{ route('docs.update', $doc->id) }}" class="modal-content">
            @csrf
            @method('PUT')

            <div class="modal-header">
                <h5 class="modal-title">Editar documento</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <div class="form-group">
                    <label>Titulo</label>
                    <input type="text" name="title" class="form-control" value="{{ $doc->title }}" required>
                </div>

                <div class="form-group">
                    <label>Categoria</label>
                    <input type="text" name="category" class="form-control" value="{{ $doc->category }}">
                </div>

                <div class="form-group">
                    <label>Descripcion</label>
                    <textarea name="description" class="form-control" rows="3">{{ $doc->description }}</textarea>
                </div>

                <div class="form-group">
                    <label>Visibilidad</label>
                    <select name="visibility" class="form-control" required>
                        @foreach(['todos' => 'Todos', 'proveedores' => 'Proveedores', 'admins' => 'Admins', 'coordventas' => 'Coord. ventas'] as $value => $label)
                            <option value="{{ $value }}" @selected($doc->visibility === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary">
                    <i class="fas fa-save"></i> Guardar
                </button>
            </div>
        </form>
    </div>
</div>
