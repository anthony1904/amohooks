<?php

namespace App;

use GuzzleHttp\Client;

require_once __DIR__ . '/../src/logger.php';

class AmoAuth
{
    protected Client $client;
    protected array $env;

    public function __construct()
    {
        $this->client = new Client();
        $this->env = $_ENV;
    }

    /**
     * Получение первичных токенов по authorization code
     */
    public function getInitialTokens(string $authCode): void
    {
        log_message('Получение первичных токенов', ['code' => $authCode]);

        $url = "https://{$this->env['AMO_BASE_DOMAIN']}/oauth2/access_token";

        try {
            $response = $this->client->post($url, [
                'headers' => ['Content-Type' => 'application/json'],
                'json' => [
                    'client_id'     => $this->env['AMO_CLIENT_ID'],
                    'client_secret' => $this->env['AMO_CLIENT_SECRET'],
                    'grant_type'    => 'authorization_code',
                    'code'          => $authCode,
                    'redirect_uri'  => $this->env['AMO_REDIRECT_URI']
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            $this->saveTokens($data);

            log_message('Первичные токены сохранены', $data);
        } catch (\Throwable $e) {
            log_message('Ошибка при получении первичных токенов', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Обновление токенов через refresh token
     */
    public function refreshTokens(): string
    {
        log_message('Обновление токенов по refresh_token');

        $url = "https://{$this->env['AMO_BASE_DOMAIN']}/oauth2/access_token";

        try {
            $response = $this->client->post($url, [
                'headers' => ['Content-Type' => 'application/json'],
                'json' => [
                    'client_id'     => $this->env['AMO_CLIENT_ID'],
                    'client_secret' => $this->env['AMO_CLIENT_SECRET'],
                    'grant_type'    => 'refresh_token',
                    'refresh_token' => $this->env['AMO_REFRESH_TOKEN'],
                    'redirect_uri'  => $this->env['AMO_REDIRECT_URI']
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            $this->saveTokens($data);

            log_message('Токены успешно обновлены', $data);

            return $data['access_token'];
        } catch (\Throwable $e) {
            log_message('Ошибка при обновлении токенов', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return '';
        }
    }

    /**
     * Обновление переменных в .env
     */
    protected function saveTokens(array $data): void
    {
        $envPath = __DIR__ . '/../.env';
        $envFile = file_get_contents($envPath);

        $envFile = preg_replace('/AMO_ACCESS_TOKEN=.*/', 'AMO_ACCESS_TOKEN=' . $data['access_token'], $envFile);
        $envFile = preg_replace('/AMO_REFRESH_TOKEN=.*/', 'AMO_REFRESH_TOKEN=' . $data['refresh_token'], $envFile);
        $envFile = preg_replace('/AMO_TOKEN_EXPIRES=.*/', 'AMO_TOKEN_EXPIRES=' . (time() + $data['expires_in']), $envFile);

        file_put_contents($envPath, $envFile);
    }

    public function getValidAccessToken(): string
    {
        $expiresAt = (int)$this->env['AMO_TOKEN_EXPIRES'];

        if (time() < $expiresAt - 60) {
            return $this->env['AMO_ACCESS_TOKEN'];
        }

        return $this->refreshTokens();
    }
}
