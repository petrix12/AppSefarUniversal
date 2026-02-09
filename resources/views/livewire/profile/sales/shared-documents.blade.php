<div class="bg-white shadow rounded-xl p-6">
  <div class="flex items-center justify-between">
    <div>
      <h3 class="font-bold text-lg">Documentos compartidos</h3>
      <span class="text-xs text-gray-400">(últimos 20)</span>
    </div>

    <a href="{{ url('/docs') }}"
       class="csrSefar inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-600 edit-user-btn">
      Ver todos →
    </a>
  </div>

  <div class="mt-4 space-y-3">
    @if($docs->count())
      @foreach($docs as $doc)
        <a
          href="{{ url('/docs/'.$doc->id.'/download') }}"
          class="block p-3 border rounded-lg hover:bg-gray-50"
          target="_blank"
          rel="noopener"
        >
          <div class="text-sm font-semibold">
            <i class="fas fa-file mr-1"></i> {{ $doc->title ?? $doc->name ?? 'Documento' }}
          </div>
          <div class="text-xs text-gray-500">
            Compartido: {{ optional($doc->created_at)->format('d/m/Y') }}
          </div>
        </a>
      @endforeach
    @else
      <div class="border rounded-lg p-4 text-gray-400">
        Aún no hay documentos
      </div>
    @endif
  </div>
</div>
