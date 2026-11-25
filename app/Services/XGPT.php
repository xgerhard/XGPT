<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\UserSettings;
use xgerhard\nbheaders\NBHeaders;

/**
 * Answers a viewer's question with the settings of the channel
 */
class XGPT
{
    private UserSettings $settings;
    private string $lengthRule;
    private ?Conversation $conversation = null;

    public function __construct(private NBHeaders $nbheaders)
    {
        $channel = $nbheaders->getChannel();
        $settingsHandler = new SettingsHandler;

        $this->settings = $settingsHandler->getUserWithSettings($channel->provider, $channel->providerId)->settings;
        $this->lengthRule = $settingsHandler->getLengthRule($channel->provider);
    }

    /**
     * Continues the conversation with this code, if it exists
     */
    public function continueConversation(string $id): void
    {
        $this->conversation = Conversation::find($id);
    }

    /**
     * Continues the last conversation of the viewer, if there is one
     */
    public function continueLastConversation(): void
    {
        $viewer = $this->viewerKey();
        if ($viewer) {
            $this->conversation = Conversation::lastOf($viewer);
        }
    }

    /**
     * Returns the chat message that answers the question
     */
    public function reply(string $question): string
    {
        $this->addQuestion($question);

        $answer = (new OpenAI)->ask($this->prompt());

        $message = $this->chatMessage($answer);
        $this->conversation->addMessage('assistant', $message->shownAnswer());
        $this->sendLater($message->second());

        return $message->first();
    }

    private function addQuestion(string $question): void
    {
        if ($this->conversation) {
            $this->conversation->touch();
        } else {
            $this->conversation = Conversation::start();
        }

        $viewer = $this->viewerKey();
        if ($viewer) {
            $this->conversation->rememberFor($viewer);
        }

        $this->conversation->addMessage('user', $question);
    }

    /**
     * The channel's instructions, the conversation, and the length rule last since that's where it works best
     */
    private function prompt(): array
    {
        return [
            ['role' => 'developer', 'content' => $this->settings->start_instructions],
            ...$this->conversation->messages()->get(['role', 'content'])->toArray(),
            ['role' => 'developer', 'content' => $this->lengthRule]
        ];
    }

    private function chatMessage(string $answer): ChatMessage
    {
        return new ChatMessage(
            $answer,
            $this->nbheaders->getProvider() == 'youtube' ? 199 : 399,
            $this->settings->mention_user ? $this->viewerName() : null,
            $this->settings->show_conversation_id ? $this->conversation->id : null,
            $this->nbheaders->getResponseUrl() !== null
        );
    }

    /**
     * Sends the message after the response, so it's the second one in chat
     */
    private function sendLater(?string $message): void
    {
        if ($message) {
            $url = $this->nbheaders->getResponseUrl();
            defer(fn () => (new NightbotAPI)->sendMessageByResponseUrl($url, $message));
        }
    }

    /**
     * Display name with badge, null on timers
     */
    private function viewerName(): ?string
    {
        $user = $this->nbheaders->getUser();
        if (!$user) {
            return null;
        }

        $badge = Badges::getBadge($user->provider, $user->providerId);

        return $badge ? '['. $badge .'] '. $user->displayName : $user->displayName;
    }

    /**
     * Identifies the viewer within the channel, null on timers
     */
    private function viewerKey(): ?string
    {
        $user = $this->nbheaders->getUser();
        $channel = $this->nbheaders->getChannel();

        return $user ? implode(':', [$channel->provider, $channel->providerId, $user->provider, $user->providerId]) : null;
    }
}
