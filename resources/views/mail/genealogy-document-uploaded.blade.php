@php
    $personName = $person ? trim($person->Nombres . ' ' . $person->Apellidos) : null;
    $documentLabel = \App\Services\GenealogyDocumentService::label($file->document_kind);
    $size = $file->size_bytes
        ? ($file->size_bytes >= 1048576
            ? number_format($file->size_bytes / 1048576, 2) . ' MB'
            : number_format($file->size_bytes / 1024, 0) . ' KB')
        : 'No disponible';
@endphp
<!doctype html>
<html lang="es">
<body style="margin:0;padding:0;background:#edf3f5;font-family:Arial,Helvetica,sans-serif;color:#19313c;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#edf3f5;padding:28px 12px;">
        <tr><td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:620px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 10px 30px rgba(14,60,76,.14);">
                <tr><td style="background:#0b5b6f;padding:26px 32px 22px;color:#ffffff;">
                    <div style="font-size:12px;letter-spacing:1.4px;text-transform:uppercase;color:#8ee4eb;font-weight:700;">Sefar Universal · Documentos</div>
                    <div style="margin-top:7px;font-size:25px;line-height:1.2;font-weight:700;">Nuevo archivo cargado</div>
                </td></tr>
                <tr><td style="padding:30px 32px 12px;">
                    <p style="margin:0 0 20px;font-size:16px;line-height:1.55;">Un cliente ha enviado un documento para revisión.</p>
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border:1px solid #d7e4e8;border-radius:12px;border-collapse:separate;overflow:hidden;">
                        <tr><td style="padding:12px 15px;background:#f5fafb;color:#60757c;font-size:12px;font-weight:700;width:38%;">CLIENTE</td><td style="padding:12px 15px;font-size:14px;font-weight:700;">{{ $user->name }}</td></tr>
                        <tr><td style="padding:12px 15px;background:#f5fafb;color:#60757c;font-size:12px;font-weight:700;">CORREO</td><td style="padding:12px 15px;font-size:14px;">{{ $user->email }}</td></tr>
                        <tr><td style="padding:12px 15px;background:#f5fafb;color:#60757c;font-size:12px;font-weight:700;">ID CLIENTE</td><td style="padding:12px 15px;font-size:14px;">{{ $user->passport }}</td></tr>
                        @if($personName)
                            <tr><td style="padding:12px 15px;background:#f5fafb;color:#60757c;font-size:12px;font-weight:700;">PERSONA DEL ÁRBOL</td><td style="padding:12px 15px;font-size:14px;">{{ $personName }}</td></tr>
                        @endif
                        <tr><td style="padding:12px 15px;background:#f5fafb;color:#60757c;font-size:12px;font-weight:700;">DOCUMENTO</td><td style="padding:12px 15px;font-size:14px;font-weight:700;color:#0b5b6f;">{{ $documentLabel }}</td></tr>
                        <tr><td style="padding:12px 15px;background:#f5fafb;color:#60757c;font-size:12px;font-weight:700;">ARCHIVO</td><td style="padding:12px 15px;font-size:14px;word-break:break-word;">{{ $file->file }}</td></tr>
                        <tr><td style="padding:12px 15px;background:#f5fafb;color:#60757c;font-size:12px;font-weight:700;">TAMAÑO</td><td style="padding:12px 15px;font-size:14px;">{{ $size }}</td></tr>
                        <tr><td style="padding:12px 15px;background:#f5fafb;color:#60757c;font-size:12px;font-weight:700;">CARGADO</td><td style="padding:12px 15px;font-size:14px;">{{ optional($file->created_at)->format('d/m/Y H:i') }}</td></tr>
                    </table>
                    <div style="margin:20px 0 4px;padding:12px 14px;border-left:4px solid #06c2cc;background:#eefbfc;color:#31525e;font-size:13px;line-height:1.45;">El documento está en revisión interna. Los archivos no se adjuntan ni se exponen en este correo.</div>
                </td></tr>
                <tr><td style="padding:22px 32px 28px;color:#71848b;font-size:12px;line-height:1.45;">Notificación automática de App Sefar Universal.</td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
