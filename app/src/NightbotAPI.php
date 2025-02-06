<?php

namespace App\src;

use Illuminate\Support\Facades\Http;

class NightbotAPI
{
    private $accessToken;
    private $baseUrl = 'https://api.nightbot.tv';

    public function setAccessToken($accessToken)
    {
        $this->accessToken = $accessToken;
        return $this;
    }

    public function sendMessageByResponseUrl($url, $message)
    {
        $message = mb_convert_encoding($message, 'UTF-8', 'UTF-8');
        return $this->request($url, 'POST', [], ['message' => $message]); 
    }

    public function getCustomCommands()
    {
        return $this->request('/1/commands');
    }

    public function editCustomCommand($id, $data)
    {
        return $this->request('/1/commands/'. $id, 'PUT', [], $data);
    }

    public function addCustomCommand($data)
    {
        return $this->request('/1/commands', 'POST', [], $data);
    }

    public function request($url, $method = 'GET', $parameters = [], $data = [])
    {
        if ($method == 'POST') {
            // This can probably be written better
            if ($this->accessToken) {
                $url = $this->baseUrl . $url;
                return Http::withToken($this->accessToken)->accept('application/json')->post(
                    $url,
                    $data
                )->json();
            } else {
                return Http::accept('application/json')->post(
                    $url,
                    $data
                )->json();
            }
        } elseif ($method == 'PUT') {
            $url = $this->baseUrl . $url;
            return Http::withToken($this->accessToken)->accept('application/json')->put(
                $url,
                $data
            )->json();
        } elseif ($method == 'GET') {
            $url = $this->baseUrl . $url;
            return Http::withToken($this->accessToken)->accept('application/json')->get($url)->json();
        }
    }
}
