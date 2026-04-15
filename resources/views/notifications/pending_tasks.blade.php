<p>A continuación se lista tu historial:</p>

<table border="1" cellpadding="5" cellspacing="0" style="width: 100%;">
    <thead>
        <tr>
            <th>Contacto</th>
            <th>Título</th>
            <th>Estado</th>
            <th>Fecha Límite</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($tasks as $task)
            <tr>
                <td>{{ $task->contact?->name ?? 'N/A' }}</td>
                <td>{{ $task->title }}</td>
                <td>{{ $task->status_label }}</td>
                <td>{{ $task->due_date }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<p>Por favor, revisa y completa tus tareas pendientes.</p>
