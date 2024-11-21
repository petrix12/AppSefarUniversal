<?php

namespace App\Services\Teamleader;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Psr\Http\Message\ResponseInterface;

class TeamleaderFocusProvider extends AbstractProvider
{
    protected function getBaseAuthorizationUrl()
    {
        return 'https://focus.teamleader.eu/oauth2/authorize';
    }

    protected function getBaseAccessTokenUrl(array $params)
    {
        return 'https://focus.teamleader.eu/oauth2/access_token';
    }

    protected function getResourceOwnerDetailsUrl($token)
    {
        // No es necesario para nuestro caso
    }

    protected function getDefaultScopes()
    {
        return [];
    }

    protected function checkResponse(ResponseInterface $response, $data)
    {
        if (isset($data['errors'])) {
            $message = $data['errors'][0]['message'] ?? $response->getReasonPhrase();
            throw new IdentityProviderException($message, $response->getStatusCode(), (string) $response->getBody());
        }
    }

    protected function createResourceOwner(array $response, $token)
    {
        return $response;
    }
}
