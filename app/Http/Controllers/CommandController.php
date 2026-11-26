<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use App\Services\XGPT;
use App\Services\Bots\BotRequest;

class CommandController extends Controller
{
    // Without a question, kept light since it can be the first thing a viewer sees from the bot
    public const NO_QUESTION_REPLIES = [
        'How can I help you today?',
        "Hey, what's up? Ask me something!",
        "Bleep bloop, I'm here. What do you need?",
        "I'm listening. Go ahead, ask away.",
        "Ready when you are. What's the question?",
        'Beep. Awaiting input. Try asking me something!',
        "Didn't catch a question there, try again?",
        'Give me something to work with! Ask me anything.',
        'Booting up... now ask me something.',
        'You rang? What can I do for you?',
        '*robot noises* Go on, ask your question.',
        "I've got answers, you've got to ask a question first.",
        "Say the word and I'll do my best to help.",
        "Still here, still listening. What's on your mind?",
    ];

    public function run(Request $request, BotRequest $bot)
    {
        if (!$request->has('q') || $request->get('q') == '') {
            return Arr::random(self::NO_QUESTION_REPLIES);
        }

        try {
            $xgpt = new XGPT($bot);

            $messageParts = explode(' ', $request->get('q'));
            if (str_starts_with($messageParts[0], '#') && strlen($messageParts[0]) == 4) {
                $xgpt->continueConversation(substr($messageParts[0], 1));
                unset($messageParts[0]);
            } elseif ($messageParts[0] == '+') {
                $xgpt->continueLastConversation();
                unset($messageParts[0]);
            }

            if (count($messageParts) == 0) {
                return Arr::random(self::NO_QUESTION_REPLIES);
            }

            $message = implode(' ', $messageParts);

            if (!$this->hasTextContent($message)) {
                return ' ';
            }

            return $xgpt->reply($message);
        } catch (\App\Exceptions\RateLimitException $e) {
            return 'Error: XGPT has reached its monthly usage limit. Support this project: https://github.com/sponsors/xgerhard ❤️';
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning($e->getMessage());
            return 'Error: The AI took too long to respond. Please try again in a bit.';
        } catch (\Throwable $e) {
            report($e);
            return 'Error: Unknown error occurred, please contact xgerhard';
        }
    }

    private function hasTextContent($text)
    {
        $text = trim(preg_replace('/\s+/', '', $text));
        $text = preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}\x{FE0E}\x{FE0F}\x{200D}\s]+/u', '', $text);
        return $text !== '';
    }
}
