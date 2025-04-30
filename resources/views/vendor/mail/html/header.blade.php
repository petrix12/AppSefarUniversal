<tr>
    <td class="header">
        <a href="{{ $url }}" style="display: inline-block;">
            @if (trim($slot) === 'Laravel')
                <img src="https://app.sefaruniversal.com/img/logo.png" class="logo" alt="Laravel Logo" style="max-width:100px;
                background: #093143 !important;
                border-radius:100px;
                margin-bottom: 15px;" height="60">
            @else
                <img src="https://app.sefaruniversal.com/img/logo.png" class="logo" alt="Logo Sefar" style="max-width:100px;
                background: #093143 !important;
                border-radius:100px;
                margin-bottom: 15px;" height="60">
                <hr>
                {{ $slot }}
            @endif
        </a>
    </td>
</tr>
