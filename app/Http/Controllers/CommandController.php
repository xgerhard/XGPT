<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\src\XGPT;

class CommandController extends Controller
{
    public function run(Request $request)
    {
        $nbheaders = new \xgerhard\nbheaders\nbheaders;

        // If APP_DEBUG is set to true in your .env, you can set a test user & channel here, so the script works from the browser.
        // This will manually set the request headers that are normally send by Nightbot urlFetch: https://docs.nightbot.tv/commands/variables/urlfetch
        if(env('APP_DEBUG'))
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

        if (!$nbheaders->isNightbotRequest()) {
            return 'Only Nightbot requests allowed.';
        }

        if (!$request->has('q') || $request->get('q') == '') {
            return 'Missing query, please ask a question? Check https://community.nightdev.com/t/custom-api-chatgpt-chat-with-your-friend-nightbot/34092 for help.';
        }

        try {
            $XGPT = new XGPT($nbheaders);

            $messageParts = explode(' ', $request->get('q'));
            if (str_starts_with($messageParts[0], '#') && strlen($messageParts[0]) == 4) {
                $XGPT->setConversionId(substr($messageParts[0], 1));
                unset($messageParts[0]);
            }

            if (count($messageParts) == 0) {
                return 'Missing query, please ask a question? Check https://community.nightdev.com/t/custom-api-chatgpt-chat-with-your-friend-nightbot/34092 for help.';
            }

            $message = implode(' ', $messageParts);

            if (!$this->hasTextContent($message)) {
                return ' ';
            }

            $XGPT->setMessage($message);
            return $XGPT->getResponse();

        } catch (\App\Exceptions\InvalidTokenException $e) {
            return 'Error: XGPT token is missing or doesn\'t match. Please visit https://xgpt.gerhard.dev/dashboard to resolve this issue.';
        } catch (\App\Exceptions\RateLimitException $e) {
            return 'Error: XGPT has reached it monthly usage limit. Support this project: https://github.com/sponsors/xgerhard ❤️';
        } catch (\App\Exceptions\InvalidApiKeyException $e) {
            return 'Error: Incorrect API key provided in your XGPT settings. You can find your API key at https://platform.openai.com/account/api-keys';
        } catch (\Illuminate\Http\Client\ConnectionException) {
            return 'Error: ChatGPT took to long to respond. Please try again in a bit. ResidentSleeper';
        } catch (\Exception $e) {
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
