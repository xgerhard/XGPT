<?php

namespace App\src;

use Illuminate\Support\Facades\Http;

class OpenAI
{
    private $baseUrl = 'https://api.openai.com/v1';

    public function getChatCompletion($post)
    {
        return $this->request('/chat/completions', 'POST', [], $post); 
    }

    public function request($path,  $method = 'GET', $parameters = [], $post = [])
    {
        return Http::accept('application/json')->withToken(env('OPENAI_API_KEY'))->post(
            $this->baseUrl . $path,
            $post
        )->json();
    }
}
