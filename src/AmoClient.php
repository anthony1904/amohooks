<?php

namespace App;

use GuzzleHttp\Client;

require_once __DIR__ . '/../src/logger.php';

class AmoClient
{
    protected Client $client;
    protected array $env;
    protected AmoAuth $auth;

    public function __construct()
    {
        $this->client = new Client();
        $this->env = $_ENV;
        $this->auth = new AmoAuth();
    }

    public function addNote(int $entityId, string $entityType, string $text): void
    {
        $accessToken = $this->auth->getValidAccessToken();
        $url = "https://{$this->env['AMO_BASE_DOMAIN']}/api/v4/{$entityType}/{$entityId}/notes";

        log_message('Добавление примечания', [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'text' => $text
        ]);

        $this->client->post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json'
            ],
            'json' => [
                [
                    'note_type' => 'common',
                    'params' => ['text' => $text]
                ]
            ]
        ]);
    }
}
