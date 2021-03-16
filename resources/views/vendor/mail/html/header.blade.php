<tr>
    <td class="header">
        <a href="{{ $url }}" style="display: inline-block;">
            @if (trim($slot) === 'Laravel')
                <img src="https://laravel.com/img/notification-logo.png" class="logo" alt="Laravel Logo">
            @else
                <img src="https://app.universalsefar.com/vendor/adminlte/dist/img/LogoSefar.png" alt="Logo Sefar" width="100" height="100">
                <hr>
                {{ $slot }}
            @endif
        </a>
    </td>
</tr>
