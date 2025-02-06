<?php

namespace App\Services;

use GuzzleHttp\Client;

class HuggingFaceAssistantService
{
    protected $client;

    public function __construct()
    {
        $this->client = new Client();
    }

    public function sendMessage($message)
    {
        $url = "https://hf.space/embed/assistant/6790e985d2933c0ad0fc807f/api/predict";

        try {
            $response = $this->client->post($url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'data' => [$message] // Este es el formato esperado por el asistente
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
