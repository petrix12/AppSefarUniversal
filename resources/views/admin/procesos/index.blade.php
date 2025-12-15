@extends('adminlte::page')

@section('title', 'Procesos')

@section('content_header')
  <h1>Procesos</h1>
@endsection

@section('content')
  <div class="card">
    <div class="card-body">

      <table class="table table-hover">
        <thead>
          <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @foreach($procesos as $p)
            <tr>
              <td>{{ $p->id }}</td>
              <td>{{ $p->nombre }}</td>
              <td class="text-right">
                <a class="btn btn-sm btn-primary"
                   href="{{ route('admin.procesos.show', $p) }}">
                  Abrir editor
                </a>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>

    </div>
  </div>
@endsection

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop
