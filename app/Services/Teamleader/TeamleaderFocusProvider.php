<?php

namespace App\Services\Teamleader;

use App\Exceptions\TeamleaderServiceUnavailableException;
use GuzzleHttp\Client;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Psr\Http\Message\ResponseInterface;

class TeamleaderFocusProvider extends AbstractProvider
{
    public function __construct(array $options = [], array $collaborators = [])
    {
        if (! isset($collaborators['httpClient'])) {
            $collaborators['httpClient'] = new Client(['timeout' => 20, 'connect_timeout' => 5]);
        }

        parent::__construct($options, $collaborators);
    }

    public function getBaseAuthorizationUrl()
    {
        return 'https://focus.teamleader.eu/oauth2/authorize';
    }

    public function getBaseAccessTokenUrl(array $params)
    {
        return 'https://focus.teamleader.eu/oauth2/access_token';
    }

    public function getResourceOwnerDetailsUrl($token)
    {
        // No es necesario para nuestro caso
    }

    protected function getDefaultScopes()
    {
        return [];
    }

    protected function getDefaultHeaders()
    {
        return ['Accept' => 'application/json'];
    }

    protected function parseResponse(ResponseInterface $response)
    {
        // The OAuth client parses the body before checkResponse. Gateways often return HTML.
        if ($response->getStatusCode() >= 500 || $response->getStatusCode() === 429) {
            throw new TeamleaderServiceUnavailableException($response->getStatusCode());
        }

        return parent::parseResponse($response);
    }

    protected function checkResponse(ResponseInterface $response, $data)
    {
        if (! is_array($data)) {
            // Do not include an HTML response body or credentials in the exception.
            throw new \UnexpectedValueException(
                'Teamleader no devolvió JSON al renovar la autorización (HTTP ' . $response->getStatusCode() . ').'
            );
        }

        if ($response->getStatusCode() >= 400 || ! empty($data['error']) || ! empty($data['errors'])) {
            $message = $data['error_description'] ?? $data['error']
                ?? $data['errors'][0]['message'] ?? $response->getReasonPhrase();
            throw new IdentityProviderException($message, $response->getStatusCode(), (string) $response->getBody());
        }
    }

    protected function createResourceOwner(array $response, $token)
    {
        return $response;
    }
}
