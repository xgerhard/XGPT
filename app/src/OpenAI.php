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

    public function createResponse($post)
    {
        return $this->request('/responses', 'POST', [], $post); 
    }

    public function request($path,  $method = 'GET', $parameters = [], $post = [])
    {
        return Http::accept('application/json')->timeout(7.5)->withToken(config('services.openai.key'))->post(
            $this->baseUrl . $path,
            $post
        )->json();
    }
}
