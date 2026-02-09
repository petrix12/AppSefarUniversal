<div class="bg-white shadow rounded-xl p-6">
  <div class="flex items-center justify-between">
    <div>
      <h3 class="font-bold text-lg">Clientes recientes</h3>
      <span class="text-xs text-gray-400">(últimos 100)</span>
    </div>

    <a href="{{ url('/users') }}"
       class="csrSefar inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-600 edit-user-btn">
      Ver todos →
    </a>
  </div>

  <div class="mt-4 space-y-2 max-h-[520px] overflow-y-auto pr-1">
    @forelse($customers as $c)
      <div class="p-2 border rounded-lg hover:bg-gray-50">
        <div class="text-sm font-semibold text-slate-900">
          {{ trim(($c->nombres ?? '').' '.($c->apellidos ?? '')) ?: ($c->name ?? 'Sin nombre') }}
        </div>
        <div class="text-[11px] text-gray-500">
          Actualizado: {{ optional($c->updated_at)->format('d/m/Y H:i') }}
        </div>
      </div>
    @empty
      <div class="border rounded-lg p-4 text-gray-400">
        No hay clientes
      </div>
    @endforelse
  </div>
</div>
