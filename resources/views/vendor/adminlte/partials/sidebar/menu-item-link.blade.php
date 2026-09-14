<li @isset($item['id']) id="{{ $item['id'] }}" @endisset class="nav-item">
    @if(!empty($item['logout']))
        <form action="{{ $item['href'] }}" method="POST" class="m-0">
            @csrf
            <button type="submit" class="nav-link {{ $item['class'] }} @isset($item['shift']) {{ $item['shift'] }} @endisset w-100 text-left border-0">
                <i class="{{ $item['icon'] ?? 'far fa-fw fa-circle' }} {{
                    isset($item['icon_color']) ? 'text-'.$item['icon_color'] : ''
                }}"></i>
                <p>{{ $item['text'] }}</p>
            </button>
        </form>
    @else
        <a class="nav-link {{ $item['class'] }} @isset($item['shift']) {{ $item['shift'] }} @endisset"
           href="{{ $item['href'] }}" @isset($item['target']) target="{{ $item['target'] }}" @endisset
           {!! $item['data-compiled'] ?? '' !!}>

            <i class="{{ $item['icon'] ?? 'far fa-fw fa-circle' }} {{
                isset($item['icon_color']) ? 'text-'.$item['icon_color'] : ''
            }}"></i>

            <p>
                {{ $item['text'] }}

                @isset($item['label'])
                    <span class="badge badge-{{ $item['label_color'] ?? 'primary' }} right">
                        {{ $item['label'] }}
                    </span>
                @endisset
            </p>

        </a>
    @endif
</li>
