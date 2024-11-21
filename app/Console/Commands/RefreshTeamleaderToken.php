<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Teamleader\TeamleaderFocusProvider;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Cache;

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
        $encryptedRefreshToken = Cache::get('teamleader_refresh_token');

        if (!$encryptedRefreshToken) {
            $this->error('No se encontró el refresh token. Por favor, autentica primero.');
            return 1;
        }

        try {
            $refreshToken = Crypt::decryptString($encryptedRefreshToken);

            $newAccessToken = $this->provider->getAccessToken('refresh_token', [
                'refresh_token' => $refreshToken,
            ]);

            // Almacenar los nuevos tokens
            Cache::put('teamleader_access_token', Crypt::encryptString($newAccessToken->getToken()), now()->addMinutes(30));
            Cache::put('teamleader_refresh_token', Crypt::encryptString($newAccessToken->getRefreshToken()), now()->addDays(30));
            Cache::put('teamleader_token_expires', $newAccessToken->getExpires(), now()->addMinutes(30));

            $this->info('Token de acceso de Teamleader actualizado exitosamente.');

            return 0;

        } catch (\Exception $e) {
            $this->error('Error al actualizar el token de acceso: ' . $e->getMessage());
            return 1;
        }
    }
}
