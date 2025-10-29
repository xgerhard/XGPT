<?php

namespace App\src;

use Illuminate\Support\Facades\Http;

class OpenAI
{
    private $apiKey;
    private $baseUrl = 'https://api.openai.com/v1';

    public function __construct($apiKey) {
        if ($apiKey && trim($apiKey) != '') {
            $this->apiKey = $apiKey;
        } else {
            $this->apiKey = env('OPENAI_API_KEY');
        }
    }

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
        return Http::accept('application/json')->timeout(7.5)->withToken($this->apiKey)->post(
            $this->baseUrl . $path,
            $post
        )->json();
    }
}
