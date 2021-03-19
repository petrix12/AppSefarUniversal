<tr>
    <td class="header">
        <a href="{{ $url }}" style="display: inline-block;">
            @if (trim($slot) === 'Laravel')
                <img src="https://laravel.com/img/notification-logo.png" class="logo" alt="Laravel Logo">
            @else
                <img src="https://1.bp.blogspot.com/-etZLVgh-Qn8/YFFkEMlCOrI/AAAAAAAAmlI/xf0ehLF8904Y3ehvw_ToKOHw1eDBUZakACLcBGAsYHQ/s0/LogoSefar_sm.png" class="logo" alt="Logo Sefar">
                <hr>
                {{ $slot }}
            @endif
        </a>
    </td>
</tr>
