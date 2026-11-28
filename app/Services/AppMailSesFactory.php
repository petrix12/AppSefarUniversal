<?php

namespace App\Services;

use App\Mail\Transports\AppSesTransport;
use Aws\SesV2\SesV2Client;
use Symfony\Component\Mailer\Transport\TransportInterface;

class AppMailSesFactory
{
    public function make(): TransportInterface
    {
        $settings = app(AppMailSettings::class);
        if (! $settings->sesReady()) throw new \RuntimeException('Amazon SES de correo de la app no está configurado.');
        $ses = $settings->ses();
        return new AppSesTransport(new SesV2Client([
            'version' => 'latest', 'region' => $ses['region'],
            'credentials' => ['key' => $ses['key'], 'secret' => $ses['secret']],
            'http' => ['connect_timeout' => 5, 'timeout' => 25],
        ]), $ses['configuration_set'] ?? null);
    }
}
