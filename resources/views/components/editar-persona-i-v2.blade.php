@props(['agclientes', 'id'])

@php
    try {
        $editar = $agclientes->where('IDPersona',$id)->first()->IDCliente;
    } catch (\Throwable $e) {
        $editar = null;
    }
@endphp

<!-- Button trigger modal -->
<button type="button" data-toggle="modal" data-target="#editarPersona{{ $id }}">
    <span title="Editar persona">
        @if ($editar)
            <i class="fas fa-user-edit text-red-500 hover:text-red-200"></i>
        @else
            <i class="fas fa-user-plus text-gray-500 hover:text-blue-500"></i>
        @endif
    </span>
</button>