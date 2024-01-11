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

        if (str_contains($request->get('q'), 'dashboard-update')) {
            return 'Hiya @'. $nbheaders->getChannel()->displayName.'! A message to notify you we updated the XGPT AI command. Personalize your AI, e.g. to respond in a certain language or to set your own API key. Visit xgpt.gerhard.dev to check it out! :)';
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

            $XGPT->setMessage(implode(' ', $messageParts));
            return $XGPT->getResponse();

        } catch (\App\Exceptions\InvalidTokenException $e) {
            return 'Error: XGPT token is missing or doesn\'t match. Please visit https://xgpt.gerhard.dev/dashboard to resolve this issue.';
        } catch (\App\Exceptions\RateLimitException $e) {
            return 'Error: Thanks for using this command! :) Unfortunately the OpenAI ChatGPT API is not free and it seems like we hit our monthly limit, this will reset first of the month. You can support this project at: https://github.com/sponsors/xgerhard ❤️';
        } catch (\App\Exceptions\InvalidApiKeyException $e) {
            return 'Error: Incorrect API key provided in your XGPT settings. You can find your API key at https://platform.openai.com/account/api-keys';
        } catch (\Illuminate\Http\Client\ConnectionException) {
            return 'Error: ChatGPT took to long to respond. Please try again in a bit. ResidentSleeper';
        } catch (\Exception $e) {
            return 'Error: Unknown error occured, please contact xgerhard';
        }
    }
}
