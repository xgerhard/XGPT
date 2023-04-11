<?php

namespace App\src;

use App\src\OpenAI;
use App\src\NightbotAPI;
use App\src\Badges;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use Str;
use Log;

class XGPT
{
    private $nbheaders = null;
    private $conversation = null;
    private $messages = [];

    public function __construct($nbheaders)
    {
        $this->openai = new OpenAI;
        $this->nbheaders = $nbheaders;

        $this->messages[] = [
            'role' => 'system',
            'content' => 'You are a helpful assistant for a '. $this->nbheaders->getProvider() .' chat.'
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
            'content' => 'Answer in 250 characters or less.'
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

        try {
            $response = $this->openai->getChatCompletion([
                'model' => 'gpt-3.5-turbo',
                'max_tokens' => 80,
                'messages' => $this->getMessages()
            ]);
        } catch (\Illuminate\Http\Client\ConnectionException) {
            return ($username ? $username .': ' : '') . ' ChatGPT took to long to respond. Please try again in a bit. ResidentSleeper';
        }

        if (isset($response['choices'][0]['message']['content'])) {
            
            $message = $response['choices'][0]['message']['content'];
            $message = trim(preg_replace('/\s+/', ' ', $message));

            $messageLength = 399;
            if ($username) {
                $messageLength -= strlen($username) + 2;
            }

            if (strlen($message) > $messageLength) {
                if ($this->nbheaders->getResponseUrl()) {
                    $secondMessageLength = 394;
                    if (strlen($message) > ($messageLength - 7 + $secondMessageLength)) {
                        $secondMessage = substr($message, $messageLength - 7, ($messageLength - 7 + $secondMessageLength) - 7) .'...';
                        $storeMessage = substr($message, 0, ($messageLength - 7 + $secondMessageLength) - 7);
                    } else {
                        $secondMessage =  substr($message, $messageLength - 7, ($messageLength - 7 + $secondMessageLength) - 4);
                        $storeMessage = substr($message, 0, ($messageLength - 7 + $secondMessageLength) - 4);
                    }

                    $this->storeMessage($storeMessage, 'assistant');
                    $secondMessage .= ' #'. $this->conversation->id;

                    session([$this->conversation->id => [
                        'responseUrl' => $this->nbheaders->getResponseUrl(),
                        'message' => $secondMessage
                    ]]);
                }
                $message = substr($message, 0, $messageLength - 7) .'...';
            } else {
                $message = substr($message, 0, $messageLength - 4);
                $this->storeMessage($message, 'assistant');
            }

            $message = ($username ? $username .': ' : '') . $message .' #'. $this->conversation->id;
            return $message;
        } else {
            Log::error(print_r($response, true));
        }

        return 'Error, unexpected response..';
    }

    public function generateConversion()
    {
        $length = 3;
        $characters = '0123456789abcdefghijklmnopqrstuvwxyz';

        while (true) {
            $string = '';
            for ($i = 0; $i < $length; $i++) {
                $index = rand(0, strlen($characters) - 1);
                $string .= $characters[$index];
            }

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
