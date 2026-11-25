<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\XGPT;
use xgerhard\nbheaders\NBHeaders;

class CommandController extends Controller
{
    public function run(Request $request, NBHeaders $nbheaders)
    {
        // If APP_DEBUG is set to true in your .env, you can set a test user & channel here, so the script works from the browser.
        // This will manually set the request headers that are normally send by Nightbot urlFetch: https://docs.nightbot.tv/commands/variables/urlfetch
        if(config('app.debug'))
        {
            $nbheaders->setUser([
                'name' => 'xgerhard',
                'displayName' => 'xgerhard',
                'provider' => 'twitch',
                'providerId' => '49056910',
                'userLevel' => 'owner'
            ]);
            $nbheaders->setChannel([
                'name' => 'xgerhard',
                'displayName' => 'xgerhard',
                'provider' => 'twitch',
                'providerId' => '49056910'
            ]);
        }

        if (!$request->has('q') || $request->get('q') == '') {
            return 'Missing query, please ask a question? Check https://community.nightdev.com/t/custom-api-chatgpt-chat-with-your-friend-nightbot/34092 for help.';
        }

        try {
            $xgpt = new XGPT($nbheaders);

            $messageParts = explode(' ', $request->get('q'));
            if (str_starts_with($messageParts[0], '#') && strlen($messageParts[0]) == 4) {
                $xgpt->continueConversation(substr($messageParts[0], 1));
                unset($messageParts[0]);
            } elseif ($messageParts[0] == '+') {
                $xgpt->continueLastConversation();
                unset($messageParts[0]);
            }

            if (count($messageParts) == 0) {
                return 'Missing query, please ask a question? Check https://community.nightdev.com/t/custom-api-chatgpt-chat-with-your-friend-nightbot/34092 for help.';
            }

            $message = implode(' ', $messageParts);

            if (!$this->hasTextContent($message)) {
                return ' ';
            }

            return $xgpt->reply($message);
        } catch (\App\Exceptions\RateLimitException $e) {
            return 'Error: XGPT has reached it monthly usage limit. Support this project: https://github.com/sponsors/xgerhard ❤️';
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning($e->getMessage());
            return 'Error: ChatGPT took to long to respond. Please try again in a bit. ResidentSleeper';
        } catch (\Throwable $e) {
            report($e);
            return 'Error: Unknown error occured, please contact xgerhard';
        }
    }

    private function hasTextContent($text)
    {
        $text = trim(preg_replace('/\s+/', '', $text));
        $text = preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}\x{FE0E}\x{FE0F}\x{200D}\s]+/u', '', $text);
        return $text !== '';
    }
}
