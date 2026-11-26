<?php

namespace App\Services;

use App\Exceptions\RateLimitException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAI
{
    private const URL = 'https://api.openai.com/v1/responses';

    /**
     * Sends the messages to the model and returns its answer as a single line of text
     *
     * @throws RateLimitException When the usage limit is reached
     * @throws RuntimeException When there is no answer
     */
    public function ask(array $messages): string
    {
        $response = Http::acceptJson()->timeout(7.5)->withToken(config('services.openai.key'))->post(self::URL, [
            'model' => 'gpt-6-luna',
            'input' => $messages,
            'max_output_tokens' => 500,
            'reasoning' => [
                'effort' => 'none'
            ]
        ]);

        if ($response->json('error.type') === 'insufficient_quota') {
            throw new RateLimitException();
        }

        $answer = $this->text($response->json('output') ?? []);

        if ($answer === '') {
            throw new RuntimeException('No answer from OpenAI, status '. $response->status() .': '. json_encode(
                $response->json('error') ?? $response->json('incomplete_details')
            ));
        }

        return $answer;
    }

    // Includes incomplete messages, those reached the token limit but still have text
    private function text(array $output): string
    {
        $text = collect($output)
            ->where('type', 'message')
            ->flatMap(fn ($message) => $message['content'])
            ->where('type', 'output_text')
            ->implode('text', '');

        return trim(preg_replace('/\s+/', ' ', $text));
    }
}
