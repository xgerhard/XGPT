<?php

namespace App\src;

use App\src\OpenAI;
use App\src\NightbotAPI;
use App\src\Badges;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\src\SettingsHandler;
use App\Exceptions\InvalidTokenException;
use App\Exceptions\InvalidApiKeyException;
use App\Exceptions\RateLimitException;
use Str;
use Log;

class XGPT
{
    private $nbheaders = null;
    private $conversation = null;
    private $messages = [];

    public function __construct($nbheaders)
    {
        $this->nbheaders = $nbheaders;

        // Fetch settings for channel
        $settings = new SettingsHandler;
        $channel = $settings->getUserWithSettings(
            $nbheaders->getChannel()->provider, 
            $nbheaders->getChannel()->providerId
        );
        $this->settings = $channel->settings;

        // If API key is set, make sure the tokens match
        if (
            $this->settings->api_key &&
            (!request()->has('token') || request()->get('token') != $channel->token)
        ) {
            throw new InvalidTokenException();
        }

        // Init OpenAI API
        $this->openai = new OpenAI($this->settings->api_key);

        // AI start instructions
        $this->messages[] = [
            'role' => 'system',
            'content' => $this->settings->start_instructions
        ];
    }

    public function setConversionId($id)
    {
        $conversation = Conversation::find($id);
        if ($conversation) {
            $this->conversation = $conversation;
        }
    }

    public function setMessage($message, $role = 'user')
    {
        if (!$this->conversation) {
            $this->generateConversion();
        }

        $this->storeMessage($message, $role);
    }

    public function getMessages()
    {
        return array_merge($this->messages, $this->getConversationMessages(), [[
            'role' => 'user',
            'content' => $this->settings->end_instructions
        ]]);
    }

    public function getConversationMessages()
    {
        $messages = [];
        $conversation = Conversation::with('messages')->find($this->conversation->id);
        foreach ($conversation->messages as $message) {
            $messages[] = [
                'role' => $message->role,
                'content' => $message->content
            ];
        }

        return $messages;
    }

    public function storeMessage($message, $role)
    {
        ConversationMessage::create([
            'role' => $role,
            'content' => $message,
            'conversation_id' => $this->conversation->id
        ]);
    }

    public function getResponse()
    {
        $username = false;
        if ($this->nbheaders->getUser()) {
            $user = $this->nbheaders->getUser();
            $username = $user->displayName;

            $badge = Badges::getBadge($user->provider, $user->providerId);
            if ($badge) {
                $username = '['. $badge .'] '. $username;
            }
        }

        $response = $this->openai->getChatCompletion([
            'model' => 'gpt-3.5-turbo',
            'max_tokens' => 80,
            'messages' => $this->getMessages()
        ]);

        if (isset($response['choices'][0]['message']['content'])) {
            
            $message = $response['choices'][0]['message']['content'];
            $message = trim(preg_replace('/\s+/', ' ', $message));
            $conversionIdLength = $this->settings->show_conversation_id ? 4 : 0;

            $messageLength = 399;
            if ($username && $this->settings->mention_user) {
                $messageLength -= strlen($username) + 2;
            }

            // If the response message is too long, we can send a second message.
            if (strlen($message) > $messageLength) {
                if ($this->nbheaders->getResponseUrl()) {
                    $secondMessageLength = 399;

                    // These calculations made my head spin..
                    // $secondMessageStartPosition (end of first message)= First message max characters, minus conversionId length, minus 3 (...).
                    $secondMessageStartPosition = $messageLength - $conversionIdLength - 3;
                    // $secondMessageEndPosition = $secondMessageStartPosition + Second message max characters, minus conversionId length.
                    $secondMessageEndPosition = $secondMessageStartPosition + $secondMessageLength - $conversionIdLength;

                    // If even the second message if too long, we cut it short and add ... at the end.
                    if (strlen($message) > ($secondMessageStartPosition + $secondMessageLength)) {
                        $secondMessage = substr($message, $secondMessageStartPosition, $secondMessageEndPosition - 3) .'...';
                        $storeMessage = substr($message, 0, $secondMessageEndPosition - 3);
                    } else {
                        $secondMessage =  substr($message, $secondMessageStartPosition, $secondMessageEndPosition);
                        $storeMessage = substr($message, 0, $secondMessageEndPosition);
                    }

                    $this->storeMessage($storeMessage, 'assistant');
                    if ($this->settings->show_conversation_id) {
                        $secondMessage .= ' #'. $this->conversation->id;
                    }

                    session(['conversion_id' => $this->conversation->id]);
                    session([$this->conversation->id => [
                        'responseUrl' => $this->nbheaders->getResponseUrl(),
                        'message' => $secondMessage
                    ]]);
                }
                $message = substr($message, 0, $secondMessageStartPosition) .'...';
            } else {
                $message = substr($message, 0, $messageLength - 4);
                $this->storeMessage($message, 'assistant');
            }

            if ($this->settings->mention_user) {
                $message = ($username ? $username .': ' : '') . $message;
            }

            if ($this->settings->show_conversation_id) {
                $message = $message .' #'. $this->conversation->id;
            }

            return $message;
        } else {
            $message = 'Error, unexpected response..';
            if (isset($response['error']['type'])) {
                switch ($response['error']['type']) {
                    case 'insufficient_quota':
                        throw new RateLimitException();
                    break;

                    case 'invalid_request_error':
                        if ($response['error']['code'] == 'invalid_api_key') {
                            // Log for a bit, to help channels with the setup
                            Log::error(print_r([
                                'name' => 'Invalid API key',
                                'provider' => $this->nbheaders->getProvider(),
                                'channel' => $this->nbheaders->getChannel()->name
                            ], true));
                            throw new InvalidApiKeyException();
                        }
                    break;

                    default:
                        Log::error(print_r($response, true));
                }
            }
            $message = ($username ? $username .': ' : '') .'Error: '.  $message;
            return $message;
        }
    }

    public function generateConversion()
    {
        while (true) {
            $string = generate_random_string(
                3, // length
                true, // letters
                true, // numbers
                true // hide similar characters
            );

            $conversation = Conversation::find($string);
            if (!$conversation) {
                break;
            }
        }

        $this->conversation = Conversation::create([
            'id' => $string
        ]);
    }
}
