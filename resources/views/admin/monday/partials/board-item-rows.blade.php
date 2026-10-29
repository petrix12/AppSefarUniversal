@foreach($items as $row)
    @include('admin.monday.partials.item-row', ['row' => $row, 'columns' => $columns, 'subitem' => false])
    @foreach($row->subitems as $subitem)
        @include('admin.monday.partials.item-row', ['row' => $subitem, 'columns' => $columns, 'subitem' => true])
    @endforeach
    @if($row->subitems_count > $row->subitems->count())
        <tr><td colspan="{{ $columns->count() + 2 }}" class="pl-4"><a href="{{ route('admin.monday.items.show', $row->id) }}">Ver los {{ number_format($row->subitems_count) }} subelementos de {{ $row->name }}</a></td></tr>
    @endif
@endforeach
