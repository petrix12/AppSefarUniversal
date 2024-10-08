<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estado del Cupón</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        html, body {
            margin:0;
            padding:0;
            border:0;
        }
        body {
            background-image: url('/img/bglogin.png');
            background-size: cover;
            background-repeat: no-repeat;
            font-family: Arial, sans-serif;
        }
    </style>
</head>
<body>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            Swal.fire({
                title: '{{ $title }}',
                text: '{{ $message }}',
                icon: '{{ $icon }}',
                confirmButtonText: 'Aceptar'
            }).then(() => {
                window.close();
            });
        });
    </script>
</body>
</html>
