@php
    $values = $row->values->keyBy('monday_column_id');
@endphp
<tr class="{{ $subitem ? 'subitem-row' : '' }}">
    <td class="item-column sticky-col">
        <a class="monday-item-link" href="{{ route('admin.monday.items.show', $row->id) }}">
            @if($subitem)<i class="fas fa-level-up-alt fa-rotate-90 text-muted mr-2"></i>@else<i class="far fa-square text-muted mr-2"></i>@endif
            {{ $row->name ?: 'Elemento '.$row->id }}
        </a>
        <small class="text-muted ml-1">#{{ $row->id }}</small>
    </td>
    @foreach($columns as $column)
        @php
            $cell = $values->get($column->monday_id);
            $cellText = $cell ? trim((string) $cell->text) : '';
            $cellColor = data_get($cell?->value, 'label.style.color') ?: data_get($cell?->value, 'label.color');
        @endphp
        <td title="{{ $cellText }}">
            @if($cellText === '')
                <span class="text-muted">—</span>
            @elseif($column->type === 'color' || $cell?->type === 'color')
                <span class="monday-status" style="background:{{ $cellColor ?: '#579bfc' }}">{{ $cellText }}</span>
            @else
                <span class="monday-cell-text">{{ $cellText }}</span>
            @endif
        </td>
    @endforeach
    <td class="text-nowrap text-muted">{{ $row->monday_updated_at?->format('d/m/Y H:i') ?: '—' }}</td>
</tr>

