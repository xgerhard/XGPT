<?php

namespace App\src;

use App\src\OpenAI;
use App\src\NightbotAPI;
use App\src\Badges;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\src\SettingsHandler;
use App\Exceptions\RateLimitException;
use Illuminate\Support\Facades\Cache;
use Str;
use Log;

class XGPT
{
    private $nbheaders = null;
    private $conversation = null;
    private $messages = [];
    private $settings;
    private $endInstructions;
    private $openai;

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
        $this->endInstructions = $settings->getEndInstructions($nbheaders->getChannel()->provider);

        // Init OpenAI API
        $this->openai = new OpenAI;

        // AI start instructions
        $this->messages[] = [
            'role' => 'developer',
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

    public function continueLastConversation()
    {
        $key = $this->getViewerKey();
        if ($key) {
            $this->setConversionId(Cache::store('conversations')->get($key));
        }
    }

    // Identifies the viewer within the channel, timers have no user
    private function getViewerKey()
    {
        $user = $this->nbheaders->getUser();
        $channel = $this->nbheaders->getChannel();

        return $user ? implode(':', ['conversation', $channel->provider, $channel->providerId, $user->provider, $user->providerId]) : null;
    }

    public function setMessage($message, $role = 'user')
    {
        if (!$this->conversation) {
            $this->generateConversion();
        } else {
            $this->conversation->touch();
        }

        $key = $this->getViewerKey();
        if ($key) {
            Cache::store('conversations')->put($key, $this->conversation->id, now()->addHours(2));
        }

        $this->storeMessage($message, $role);
    }

    public function getMessages()
    {
        return array_merge($this->messages, $this->getConversationMessages(), [[
            'role' => 'developer',
            'content' => $this->endInstructions
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

        $response = $this->openai->createResponse([
            'model' => 'gpt-5-nano',
            'input' => $this->getMessages(),
            'reasoning' => [
                'effort' => 'minimal'
            ]
        ]);

        if (isset($response['output'])) {
            $message = '';
            foreach ($response['output'] as $item) {
                if (($item['type'] ?? '') === 'message' && ($item['status'] ?? '') === 'completed') {
                    foreach ($item['content'] as $contentPart) {
                        if (($contentPart['type'] ?? '') === 'output_text') {
                            $message .= $contentPart['text'];
                        }
                    }
                }
            }

            $message = trim(preg_replace('/\s+/', ' ', $message));
            $responseUrl = $this->nbheaders->getResponseUrl();
            $chatMessage = new ChatMessage(
                $message,
                $this->nbheaders->getProvider() == 'youtube' ? 199 : 399,
                $this->settings->mention_user && $username ? $username : null,
                $this->settings->show_conversation_id ? $this->conversation->id : null,
                (bool) $responseUrl
            );

            $this->storeMessage($chatMessage->shownAnswer(), 'assistant');

            if ($chatMessage->second()) {
                session(['conversion_id' => $this->conversation->id]);
                session([$this->conversation->id => [
                    'responseUrl' => $responseUrl,
                    'message' => $chatMessage->second()
                ]]);
            }

            return $chatMessage->first();
        } else {
            $message = 'Error, unexpected response..';
            if (isset($response['error']['type'])) {
                switch ($response['error']['type']) {
                    case 'insufficient_quota':
                        throw new RateLimitException();
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
                false // numbers
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
