<?php

namespace App\Providers;

use Exception;
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\Facades\Storage;
use Justijndepover\Teamleader\Teamleader;

class TeamleaderServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register()
    {
        $this->app->singleton(Teamleader::class, function ($app) {
            $teamleader = new Teamleader(
                config('services.teamleader.client_id'),
                config('services.teamleader.client_secret'),
                config('services.teamleader.redirect_uri'),
                config('services.teamleader.state'),
            );

            $teamleader->setTokenUpdateCallback(function ($teamleader) {
                Storage::disk('local')->put('teamleader.json', json_encode([
                    'accessToken' => $teamleader->getAccessToken(),
                    'refreshToken' => $teamleader->getRefreshToken(),
                    'expiresAt' => $teamleader->getTokenExpiresAt(),
                ]));
            });

            if (Storage::exists('teamleader.json') && $json = Storage::get('teamleader.json')) {
                try {
                    $json = json_decode($json);
                    $teamleader->setAccessToken($json->accessToken);
                    $teamleader->setRefreshToken($json->refreshToken);
                    $teamleader->setTokenExpiresAt($json->expiresAt);
                } catch (Exception $e) {
                }
            }

            if (! empty($teamleader->getRefreshToken()) && $teamleader->shouldRefreshToken()) {
                try {
                    $teamleader->connect();
                } catch (\Throwable $th) {
                    $teamleader->setRefreshToken('');

                    Storage::disk('local')->put('teamleader.json', json_encode([
                    'accessToken' => $teamleader->getAccessToken(),
                    'refreshToken' => $teamleader->getRefreshToken(),
                    'expiresAt' => $teamleader->getTokenExpiresAt(),
                ]));
                }
            }

            return $teamleader;
        });
    }

    public function provides()
    {
        return [Teamleader::class];
    }
}