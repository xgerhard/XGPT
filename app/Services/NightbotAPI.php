<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class NightbotAPI
{
    /**
     * Sends a message to the chat with the response url of a command
     */
    public function sendMessageByResponseUrl(string $url, string $message)
    {
        return Http::acceptJson()->post($url, ['message' => mb_convert_encoding($message, 'UTF-8', 'UTF-8')])->json();
    }
}
