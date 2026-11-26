<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\UserSettings;
use App\Services\Bots\BotRequest;

/**
 * Answers a viewer's question with the settings of the channel
 */
class XGPT
{
    private UserSettings $settings;
    private string $instructions;
    private string $lengthRule;
    private ?Conversation $conversation = null;

    public function __construct(private BotRequest $bot)
    {
        $settingsHandler = new SettingsHandler;

        $this->settings = $settingsHandler->getUserWithSettings($bot->channelProvider, $bot->channelId)->settings;
        $this->instructions = $this->settings->start_instructions ?: $settingsHandler->getStartInstructions($bot->channelProvider);
        $this->lengthRule = $settingsHandler->getLengthRule($bot->channelProvider);
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
        $viewer = $this->bot->viewerKey();
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
        $this->conversation->addMessage('assistant', $message->storedAnswer());
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

        $viewer = $this->bot->viewerKey();
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
            ['role' => 'developer', 'content' => $this->instructions],
            ...$this->conversation->messages()->get(['role', 'content'])->toArray(),
            ['role' => 'developer', 'content' => $this->lengthRule]
        ];
    }

    private function chatMessage(string $answer): ChatMessage
    {
        return new ChatMessage(
            $answer,
            $this->bot->platform() == 'youtube' ? 199 : 399,
            $this->settings->mention_user ? $this->viewerName() : null,
            $this->settings->show_conversation_id ? $this->conversation->id : null,
            $this->bot->responseUrl !== null
        );
    }

    /**
     * Sends the message after the response, so it's the second one in chat
     */
    private function sendLater(?string $message): void
    {
        if ($message) {
            $url = $this->bot->responseUrl;
            defer(fn () => (new NightbotAPI)->sendMessageByResponseUrl($url, $message));
        }
    }

    /**
     * Display name with badge, null on timers
     */
    private function viewerName(): ?string
    {
        if (!$this->bot->hasUser()) {
            return null;
        }

        $badge = Badges::getBadge($this->bot->userProvider, $this->bot->userId);

        return $badge ? '['. $badge .'] '. $this->bot->userName : $this->bot->userName;
    }
}
