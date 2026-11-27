<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class CheckGoogleAuthentication extends Command
{
    protected $signature = 'google:check';
    protected $description = 'Comprueba la configuración de acceso con Google sin mostrar secretos ni iniciar sesión';

    public function handle(): int
    {
        $ready = true;
        foreach (['client_id' => 'GOOGLE_CLIENT_ID', 'client_secret' => 'GOOGLE_CLIENT_SECRET'] as $key => $label) {
            $present = filled(config('services.google.'.$key));
            $ready = $ready && $present;
            $this->line($label.': '.($present ? 'configurado' : 'pendiente'));
        }
        $callback = (string) config('services.google.redirect');
        $matches = $callback === route('register.v2.google.callback');
        $ready = $ready && $matches;
        $this->line('Retorno OAuth: '.$callback);
        $this->line('Coincide con esta instalación: '.($matches ? 'sí' : 'no; revisar APP_URL y GOOGLE_REDIRECT_URI'));
        $schema = Schema::hasTable('user_google_identities');
        $ready = $ready && $schema;
        $this->line('Vinculación de cuentas: '.($schema ? 'migración aplicada' : 'falta la tabla user_google_identities; ejecutar php artisan migrate'));
        $this->line('Permisos solicitados: openid profile email. El acceso real debe comprobarse en el navegador.');
        return $ready ? self::SUCCESS : self::FAILURE;
    }
}
