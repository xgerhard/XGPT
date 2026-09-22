<?php

namespace App\Chat\Providers;

use Illuminate\Support\Facades\Http;

/**
 * Thin client for Nightbot's account-linked API (distinct from the customapi-style headers
 * Nightbot attaches to /api/command requests, which carry no auth at all — see
 * NightbotBotProvider). Ported near-verbatim from the pre-rewrite App\src\NightbotAPI.
 */
class NightbotApiClient
{
    private ?string $accessToken = null;

    private string $baseUrl = 'https://api.nightbot.tv';

    public function setAccessToken(string $accessToken): static
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    public function getCustomCommands(): array
    {
        return $this->request('/1/commands');
    }

    public function editCustomCommand(string $id, array $data): array
    {
        return $this->request('/1/commands/'.$id, 'PUT', $data);
    }

    public function addCustomCommand(array $data): array
    {
        return $this->request('/1/commands', 'POST', $data);
    }

    /**
     * Send a message via a Nightbot-Response-Url. The caller (NightbotBotProvider) is
     * responsible for having already verified the URL's host — this client makes no assumption
     * about where $url points.
     */
    public function sendMessageByResponseUrl(string $url, string $message): array
    {
        $message = mb_convert_encoding($message, 'UTF-8', 'UTF-8');

        return Http::acceptJson()->post($url, ['message' => $message])->json() ?? [];
    }

    private function request(string $path, string $method = 'GET', array $data = []): array
    {
        $request = Http::acceptJson()->withToken($this->accessToken);

        $response = match ($method) {
            'POST' => $request->post($this->baseUrl.$path, $data),
            'PUT' => $request->put($this->baseUrl.$path, $data),
            default => $request->get($this->baseUrl.$path),
        };

        return $response->json() ?? [];
    }
}
