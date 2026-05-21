@php
    $taskUrl = route('tasks.show', $task);
@endphp

<p>Hola,</p>

<p>
    Este contacto fue marcado como contactado y debe salir del circuito de reasignacion
    para evitar que lo llamen de nuevo.
</p>

<table cellpadding="6" cellspacing="0" border="0">
    <tr>
        <td><strong>Contacto</strong></td>
        <td>{{ $contact->name ?? 'Sin nombre' }}</td>
    </tr>
    <tr>
        <td><strong>Email</strong></td>
        <td>{{ $contact->email ?? '-' }}</td>
    </tr>
    <tr>
        <td><strong>Telefono</strong></td>
        <td>{{ $contact->phone ?? '-' }}</td>
    </tr>
    <tr>
        <td><strong>HubSpot ID</strong></td>
        <td>{{ $contact->hs_id ?? '-' }}</td>
    </tr>
    <tr>
        <td><strong>Tarea</strong></td>
        <td>#{{ $task->id }} - {{ $task->title }}</td>
    </tr>
</table>

<p><strong>Listas marcadas como contactadas:</strong></p>

<ul>
    @foreach($lists as $list)
        <li>{{ $list->name }}</li>
    @endforeach
</ul>

<p>
    <a href="{{ $taskUrl }}">Ver tarea</a>
</p>

<p>Este mensaje fue generado automaticamente.</p>
