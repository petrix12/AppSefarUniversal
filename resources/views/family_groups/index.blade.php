@extends('adminlte::page')

@section('title', 'Grupos familiares')

@section('content_header')
@stop

@section('content')
<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="family-groups-header">
                    <div>
                        <div class="family-groups-eyebrow">Coincidencias entre arboles</div>
                        <h1 class="family-groups-title">Grupos familiares</h1>
                    </div>
                    <a href="{{ route('family-groups.create') }}" class="family-groups-primary-button">
                        Crear grupo familiar
                    </a>
                </div>

                <form class="family-groups-search" method="GET" action="{{ route('family-groups.index') }}">
                    <input type="text" name="search" value="{{ $search }}" placeholder="Buscar por grupo, IDCliente o clave de coincidencia">
                    <button type="submit">Buscar</button>
                    @if($search !== '')
                        <a href="{{ route('family-groups.index') }}">Limpiar</a>
                    @endif
                </form>

                <div class="family-groups-table-wrap">
                    <table class="family-groups-table">
                        <thead>
                            <tr>
                                <th>Grupo</th>
                                <th>IDCliente base</th>
                                <th>Clave</th>
                                <th>Miembros</th>
                                <th>Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($groups as $group)
                                <tr>
                                    <td>
                                        <strong>{{ $group->name }}</strong>
                                        <span>{{ optional($group->updated_at)->format('d/m/Y H:i') }}</span>
                                    </td>
                                    <td>{{ $group->primary_id_cliente }}</td>
                                    <td>{{ $group->match_key ?: 'Sin clave' }}</td>
                                    <td>{{ $group->members_count }}</td>
                                    <td>{{ $group->status }}</td>
                                    <td class="family-groups-actions">
                                        <a href="{{ route('family-groups.show', $group) }}">Ver</a>
                                        <form action="{{ route('family-groups.destroy', $group) }}" method="POST" onsubmit="return confirm('¿Eliminar este grupo familiar?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit">Eliminar</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="family-groups-empty">No hay grupos familiares registrados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="family-groups-pagination">
                    {{ $groups->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
    <style>
        .family-groups-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 24px;
            border-bottom: 1px solid rgba(9, 49, 67, 0.12);
        }
        .family-groups-eyebrow {
            color: #607783;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .family-groups-title {
            margin: 4px 0 0;
            color: #093143;
            font-size: 1.8rem;
            font-weight: 800;
        }
        .family-groups-primary-button,
        .family-groups-search button,
        .family-groups-actions a,
        .family-groups-actions button {
            background: #093143;
            color: white;
            border-radius: 8px;
            padding: 9px 14px;
            font-size: 0.85rem;
            font-weight: 700;
            border: 0;
        }
        .family-groups-search {
            display: grid;
            grid-template-columns: minmax(260px, 1fr) auto auto;
            gap: 10px;
            padding: 18px 24px;
        }
        .family-groups-search input {
            border: 1px solid #cbd5dc;
            border-radius: 8px;
            color: #093143;
            padding: 9px 12px;
        }
        .family-groups-search a {
            display: inline-flex;
            align-items: center;
            color: #093143;
            font-weight: 700;
        }
        .family-groups-table-wrap {
            overflow-x: auto;
            padding: 0 24px 24px;
        }
        .family-groups-table {
            width: 100%;
            border-collapse: collapse;
            color: #093143;
        }
        .family-groups-table th {
            text-align: left;
            font-size: 0.72rem;
            text-transform: uppercase;
            color: #607783;
            padding: 10px;
            border-bottom: 1px solid rgba(9, 49, 67, 0.12);
        }
        .family-groups-table td {
            padding: 12px 10px;
            border-bottom: 1px solid rgba(9, 49, 67, 0.08);
            vertical-align: middle;
        }
        .family-groups-table td span {
            display: block;
            color: #607783;
            font-size: 0.76rem;
            margin-top: 3px;
        }
        .family-groups-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }
        .family-groups-actions button {
            background: #7a1f1f;
        }
        .family-groups-empty,
        .family-groups-pagination {
            padding: 18px 24px;
            color: #607783;
        }
        @media (max-width: 760px) {
            .family-groups-header,
            .family-groups-search {
                grid-template-columns: 1fr;
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
@stop
