<div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h3 class="text-lg font-semibold text-slate-800">
                Tareas pendientes
            </h3>
            <p class="text-sm text-slate-500">
                Lo primero que debes atender hoy
            </p>
        </div>

        <a href="{{ route('tasks.index') }}"
           class="text-sm font-medium text-blue-600 hover:text-blue-800">
            Ver todas
        </a>
    </div>

    @forelse ($tasks as $task)
        @php
            $isOverdue = $task->due_date->lt(today());
            $isToday = $task->due_date->isToday();
        @endphp

        <div class="py-3 border-b border-slate-100 last:border-b-0">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="font-medium text-slate-800">
                        {{ $task->title }}
                    </p>

                    @if($task->contact)
                        <p class="text-sm text-slate-500">
                            Cliente: {{ $task->contact?->name }}
                        </p>
                    @endif

                    <p class="text-xs mt-1 {{ $isOverdue ? 'text-red-500' : ($isToday ? 'text-amber-500' : 'text-slate-400') }}">
                        @if($isOverdue)
                            Vencida: {{ $task->due_date->format('d/m/Y') }}
                        @elseif($isToday)
                            Vence hoy
                        @else
                            Vence: {{ $task->due_date->format('d/m/Y') }}
                        @endif
                    </p>
                </div>

                <a href="{{ route('tasks.show', $task) }}"
                   class="text-sm text-slate-600 hover:text-slate-900">
                    Abrir
                </a>
            </div>
        </div>
    @empty
        <p class="text-sm text-slate-500">
            No tienes tareas pendientes.
        </p>
    @endforelse
</div>
