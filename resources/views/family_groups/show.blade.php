@extends('adminlte::page')

@section('title', 'Grupo familiar')

@section('content_header')
@stop

@section('content')
<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="family-group-header">
                    <div>
                        <div class="family-group-eyebrow">Grupo familiar</div>
                        <h1 class="family-group-title">{{ $familyGroup->name }}</h1>
                        <p>IDCliente base: {{ $familyGroup->primary_id_cliente ?: 'Sin cliente base' }}</p>
                    </div>
                    <div class="family-group-header-actions">
                        <a href="{{ route('family-groups.index') }}">Volver</a>
                        <form action="{{ route('family-groups.recalculate', $familyGroup) }}" method="POST">
                            @csrf
                            <button type="submit">Recalcular coincidencias</button>
                        </form>
                    </div>
                </div>

                @if($familyGroup->notes)
                    <div class="family-group-notes">{{ $familyGroup->notes }}</div>
                @endif

                <div class="family-group-panels">
                    <section class="family-group-panel">
                        <div class="family-group-panel-title">Agregar cliente al grupo</div>
                        <form method="POST" action="{{ route('family-groups.members.store', $familyGroup) }}" class="family-inline-form">
                            @csrf
                            <input type="text" name="IDCliente" value="{{ old('IDCliente') }}" placeholder="IDCliente / pasaporte">
                            <button type="submit">Agregar</button>
                        </form>
                        @error('IDCliente')
                            <div class="family-group-error">{{ $message }}</div>
                        @enderror
                    </section>

                    <section class="family-group-panel">
                        <div class="family-group-panel-title">Buscar coincidencias</div>
                        <form method="GET" action="{{ route('family-groups.show', $familyGroup) }}" class="family-inline-form">
                            <input type="text" name="candidate_search" value="{{ $candidateSearch }}" placeholder="Nombre, apellido, pasaporte o IDCliente">
                            <button type="submit">Buscar</button>
                        </form>
                    </section>
                </div>

                <section class="family-group-section">
                    <div class="family-group-section-header">
                        <h2>Miembros del grupo</h2>
                        <span>{{ $familyGroup->members->count() }} cliente(s)</span>
                    </div>

                    <div class="family-group-table-wrap">
                        <table class="family-group-table">
                            <thead>
                                <tr>
                                    <th>Cliente</th>
                                    <th>Origen</th>
                                    <th>Confianza</th>
                                    <th>Evidencia</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($familyGroup->members as $member)
                                    <tr>
                                        <td>
                                            <strong>{{ $member->display_name ?: $member->IDCliente }}</strong>
                                            <span>{{ $member->IDCliente }}</span>
                                        </td>
                                        <td>
                                            {{ $member->source }}
                                            @if($member->match_type)
                                                <span>{{ $member->match_type }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="family-confidence">
                                                <span style="width: {{ min(100, max(0, (int) $member->confidence)) }}%;"></span>
                                            </div>
                                            <small>{{ $member->confidence }}%</small>
                                        </td>
                                        <td>
                                            @if(!empty($member->match_reasons))
                                                <ul class="family-evidence-list">
                                                    @foreach($member->match_reasons as $reason)
                                                        <li>{{ $reason }}</li>
                                                    @endforeach
                                                </ul>
                                            @else
                                                <span>Agregado manualmente</span>
                                            @endif
                                        </td>
                                        <td class="family-member-actions">
                                            <a href="{{ route('arboles.tree.index', $member->IDCliente) }}" target="_blank">Arbol</a>
                                            <form action="{{ route('family-groups.members.destroy', [$familyGroup, $member]) }}" method="POST" onsubmit="return confirm('¿Sacar este cliente del grupo familiar?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit">Sacar</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="family-empty">Este grupo no tiene miembros.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="family-group-section">
                    <div class="family-group-section-header">
                        <h2>Candidatos calculados</h2>
                        <span>{{ $candidates->count() }} coincidencia(s)</span>
                    </div>

                    <div class="family-candidates">
                        @forelse($candidates as $candidate)
                            <article class="family-candidate">
                                <div class="family-candidate-main">
                                    <div>
                                        <h3>{{ $candidate['name'] }}</h3>
                                        <p>IDCliente {{ $candidate['IDCliente'] }} / {{ $candidate['score'] }}% confianza</p>
                                    </div>
                                    <form method="POST" action="{{ route('family-groups.members.store', $familyGroup) }}">
                                        @csrf
                                        <input type="hidden" name="IDCliente" value="{{ $candidate['IDCliente'] }}">
                                        <button type="submit">Agregar al grupo</button>
                                    </form>
                                </div>

                                <div class="family-candidate-reasons">
                                    @foreach($candidate['reasons'] as $reason)
                                        <span>{{ $reason }}</span>
                                    @endforeach
                                </div>

                                @if(!empty($candidate['evidence']))
                                    <div class="family-candidate-evidence">
                                        @foreach($candidate['evidence'] as $evidence)
                                            <div>
                                                <strong>{{ $evidence['name'] ?: 'Persona sin nombre' }}</strong>
                                                <span>
                                                    IDPersona {{ $evidence['IDPersona'] ?? 'N/D' }}
                                                    @if(!empty($evidence['passport']))
                                                        / Doc. {{ $evidence['passport'] }}
                                                    @endif
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </article>
                        @empty
                            <div class="family-empty">No hay candidatos calculados para los filtros actuales.</div>
                        @endforelse
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
    <style>
        .family-group-header {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            padding: 24px;
            border-bottom: 1px solid rgba(9, 49, 67, 0.12);
        }
        .family-group-eyebrow {
            color: #607783;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .family-group-title {
            margin: 4px 0;
            color: #093143;
            font-size: 1.8rem;
            font-weight: 800;
        }
        .family-group-header p {
            margin: 0;
            color: #607783;
        }
        .family-group-header-actions {
            display: flex;
            gap: 10px;
            align-items: flex-start;
        }
        .family-group-header-actions a,
        .family-group-header-actions button,
        .family-inline-form button,
        .family-member-actions a,
        .family-member-actions button,
        .family-candidate-main button {
            background: #093143;
            color: white;
            border-radius: 8px;
            padding: 9px 13px;
            font-size: 0.82rem;
            font-weight: 700;
            border: 0;
            white-space: nowrap;
        }
        .family-group-notes {
            margin: 18px 24px 0;
            padding: 12px 14px;
            border-radius: 8px;
            background: #eef6f8;
            color: #405763;
        }
        .family-group-panels {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            padding: 24px;
        }
        .family-group-panel {
            border: 1px solid rgba(9, 49, 67, 0.12);
            border-radius: 8px;
            padding: 16px;
        }
        .family-group-panel-title,
        .family-group-section-header h2 {
            margin: 0 0 12px;
            color: #093143;
            font-size: 1rem;
            font-weight: 800;
        }
        .family-inline-form {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 10px;
        }
        .family-inline-form input {
            border: 1px solid #cbd5dc;
            border-radius: 8px;
            padding: 9px 12px;
            color: #093143;
        }
        .family-group-error {
            margin-top: 8px;
            color: #a22525;
            font-weight: 700;
        }
        .family-group-section {
            padding: 0 24px 24px;
        }
        .family-group-section-header {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: center;
            margin-bottom: 10px;
        }
        .family-group-section-header span {
            color: #607783;
            font-size: 0.84rem;
            font-weight: 700;
        }
        .family-group-table-wrap {
            overflow-x: auto;
            border: 1px solid rgba(9, 49, 67, 0.12);
            border-radius: 8px;
        }
        .family-group-table {
            width: 100%;
            border-collapse: collapse;
            color: #093143;
        }
        .family-group-table th {
            text-align: left;
            font-size: 0.72rem;
            text-transform: uppercase;
            color: #607783;
            padding: 10px;
            border-bottom: 1px solid rgba(9, 49, 67, 0.12);
        }
        .family-group-table td {
            padding: 12px 10px;
            border-bottom: 1px solid rgba(9, 49, 67, 0.08);
            vertical-align: top;
        }
        .family-group-table td span,
        .family-group-table td small {
            display: block;
            color: #607783;
            font-size: 0.76rem;
            margin-top: 3px;
        }
        .family-confidence {
            width: 90px;
            height: 8px;
            border-radius: 999px;
            background: #dce7eb;
            overflow: hidden;
        }
        .family-confidence span {
            display: block;
            height: 100%;
            background: #b08a43;
        }
        .family-evidence-list {
            margin: 0;
            padding-left: 18px;
            color: #405763;
            font-size: 0.8rem;
        }
        .family-member-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }
        .family-member-actions button {
            background: #7a1f1f;
        }
        .family-candidates {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }
        .family-candidate {
            border: 1px solid rgba(9, 49, 67, 0.12);
            border-radius: 8px;
            padding: 14px;
        }
        .family-candidate-main {
            display: flex;
            justify-content: space-between;
            gap: 12px;
        }
        .family-candidate-main h3 {
            margin: 0;
            color: #093143;
            font-size: 1rem;
            font-weight: 800;
        }
        .family-candidate-main p {
            margin: 4px 0 0;
            color: #607783;
            font-size: 0.8rem;
        }
        .family-candidate-reasons {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 10px;
        }
        .family-candidate-reasons span {
            border-radius: 999px;
            background: #eef6f8;
            color: #093143;
            padding: 4px 8px;
            font-size: 0.72rem;
            font-weight: 700;
        }
        .family-candidate-evidence {
            display: grid;
            gap: 6px;
            margin-top: 12px;
        }
        .family-candidate-evidence div {
            padding: 8px;
            border-radius: 6px;
            background: #f8fafb;
            color: #093143;
        }
        .family-candidate-evidence span {
            display: block;
            margin-top: 2px;
            color: #607783;
            font-size: 0.74rem;
        }
        .family-empty {
            padding: 16px;
            color: #607783;
        }
        @media (max-width: 960px) {
            .family-group-header,
            .family-group-panels,
            .family-candidates {
                grid-template-columns: 1fr;
                flex-direction: column;
            }
            .family-group-header-actions,
            .family-candidate-main,
            .family-member-actions {
                flex-direction: column;
            }
        }
    </style>
@stop
