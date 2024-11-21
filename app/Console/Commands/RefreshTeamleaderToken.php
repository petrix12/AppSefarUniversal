<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TeamleaderToken;
use App\Services\Teamleader\TeamleaderFocusProvider;
use Illuminate\Support\Facades\Crypt;

class RefreshTeamleaderToken extends Command
{
    protected $signature = 'teamleader:refresh-token';
    protected $description = 'Actualiza el token de acceso de Teamleader';

    protected $provider;

    public function __construct()
    {
        parent::__construct();

        $this->provider = new TeamleaderFocusProvider([
            'clientId'     => env('TEAMLEADER_CLIENT_ID'),
            'clientSecret' => env('TEAMLEADER_CLIENT_SECRET'),
            'redirectUri'  => env('TEAMLEADER_REDIRECT_URI'),
        ]);
    }

    public function handle()
    {
        $tokenRecord = TeamleaderToken::find(1);

        if (!$tokenRecord) {
            $this->error('No se encontraron tokens. Por favor, autentica primero.');
            return 1;
        }

        $expires = $tokenRecord->expires;

        if (time() >= $expires) {
            try {
                $refreshToken = Crypt::decryptString($tokenRecord->refresh_token);

                $newAccessToken = $this->provider->getAccessToken('refresh_token', [
                    'refresh_token' => $refreshToken,
                ]);

                // Almacenar los nuevos tokens
                $tokenRecord->update([
                    'access_token' => Crypt::encryptString($newAccessToken->getToken()),
                    'refresh_token' => Crypt::encryptString($newAccessToken->getRefreshToken()),
                    'expires' => $newAccessToken->getExpires(),
                ]);

                $this->info('Token de acceso de Teamleader actualizado exitosamente.');
                return 0;

            } catch (\Exception $e) {
                $this->error('Error al actualizar el token de acceso: ' . $e->getMessage());
                return 1;
            }
        } else {
            $this->info('El token de acceso aún es válido.');
            return 0;
        }
    }
}
