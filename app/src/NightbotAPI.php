<?php

namespace App\src;

use Illuminate\Support\Facades\Http;

class NightbotAPI
{
    public function sendMessageByResponseUrl($url, $message)
    {
        return $this->request($url, 'POST', [], ['message' => $message]); 
    }

    public function request($url,  $method = 'GET', $parameters = [], $post = [])
    {
        return Http::accept('application/json')->post(
            $url,
            $post
        )->json();
    }
}
