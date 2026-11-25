<?php

namespace App\src;

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
            'model' => 'gpt-5-nano',
            'input' => $messages,
            'reasoning' => [
                'effort' => 'minimal'
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

    private function text(array $output): string
    {
        $text = collect($output)
            ->where('type', 'message')
            ->where('status', 'completed')
            ->flatMap(fn ($message) => $message['content'])
            ->where('type', 'output_text')
            ->implode('text', '');

        return trim(preg_replace('/\s+/', ' ', $text));
    }
}
