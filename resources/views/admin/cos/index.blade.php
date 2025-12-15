@extends('adminlte::page')

@section('title', 'Editor COS')

@section('content_header')
  <h1>Editor COS</h1>
@endsection

@section('content')
  <div class="card">
    <div class="card-body">
      <ul>
        @foreach($cosList as $cos)
          <li>
            <a href="{{ route('admin.cos.show', $cos) }}">{{ $cos->nombre }}</a>
          </li>
        @endforeach
      </ul>
    </div>
  </div>
@endsection
