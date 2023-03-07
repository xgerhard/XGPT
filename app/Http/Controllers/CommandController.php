<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\src\XGPT;

class CommandController extends Controller
{
    public function run(Request $request)
    {
        $nbheaders = new \xgerhard\nbheaders\nbheaders;
        if (!$nbheaders->isNightbotRequest()) {
            // return 'Only Nightbot requests allowed.';
        }

        if (!$request->has('q') || $request->get('q') == '') {
            return 'Missing query, please ask a question? Check https://community.nightdev.com/t/custom-api-chatgpt-chat-with-your-friend-nightbot/34092 for help.';
        }

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
    }
}
