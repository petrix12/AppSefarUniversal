<tr>
    <td class="header">
        <a href="{{ $url }}" style="display: inline-block;">
            @if (trim($slot) === 'Laravel')
                <img src="https://app.sefaruniversal.com/img/logo.png" class="logo" alt="Laravel Logo" height="60">
            @else
                <img src="https://app.sefaruniversal.com/img/logo.png" class="logo" alt="Logo Sefar" height="60">
                <hr>
                {{ $slot }}
            @endif
        </a>
    </td>
</tr>
