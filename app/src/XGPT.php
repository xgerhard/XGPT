<?php

namespace App\src;

use App\src\OpenAI;
use App\src\NightbotAPI;
use App\src\Badges;
use App\Models\Conversation;
use App\src\SettingsHandler;

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

    public function continueConversation($id)
    {
        $this->conversation = Conversation::find($id);
    }

    public function continueLastConversation()
    {
        $viewer = $this->getViewer();
        if ($viewer) {
            $this->conversation = Conversation::lastOf($viewer);
        }
    }

    // Identifies the viewer within the channel, timers have no user
    private function getViewer()
    {
        $user = $this->nbheaders->getUser();
        $channel = $this->nbheaders->getChannel();

        return $user ? implode(':', [$channel->provider, $channel->providerId, $user->provider, $user->providerId]) : null;
    }

    public function setMessage($message)
    {
        if (!$this->conversation) {
            $this->conversation = Conversation::start();
        } else {
            $this->conversation->touch();
        }

        $viewer = $this->getViewer();
        if ($viewer) {
            $this->conversation->rememberFor($viewer);
        }

        $this->conversation->addMessage('user', $message);
    }

    public function getMessages()
    {
        return array_merge(
            $this->messages,
            $this->conversation->messages()->get(['role', 'content'])->toArray(),
            [[
                'role' => 'developer',
                'content' => $this->endInstructions
            ]]
        );
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

        $message = $this->openai->ask($this->getMessages());

        $responseUrl = $this->nbheaders->getResponseUrl();
        $chatMessage = new ChatMessage(
            $message,
            $this->nbheaders->getProvider() == 'youtube' ? 199 : 399,
            $this->settings->mention_user && $username ? $username : null,
            $this->settings->show_conversation_id ? $this->conversation->id : null,
            (bool) $responseUrl
        );

        $this->conversation->addMessage('assistant', $chatMessage->shownAnswer());

        if ($chatMessage->second()) {
            // Sent after the response, so it's the second message in chat
            defer(fn () => (new NightbotAPI)->sendMessageByResponseUrl($responseUrl, $chatMessage->second()));
        }

        return $chatMessage->first();
    }
}
