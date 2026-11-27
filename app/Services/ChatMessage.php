<?php

namespace App\Services;

/**
 * Turns an answer into one chat message, or two when it doesn't fit:
 * "Name: start of the answer... #abc" and "rest of the answer #abc"
 */
class ChatMessage
{
    private const ELLIPSIS = '...';

    private string $first;
    private ?string $second = null;
    private string $storedAnswer;

    /**
     * @param string $answer
     * @param int $maxLength Maximum characters per chat message
     * @param string|null $name Viewer to mention
     * @param string|null $code Conversation code to show
     * @param bool $canSplit Whether a second message can be sent
     */
    public function __construct(string $answer, int $maxLength, ?string $name = null, ?string $code = null, bool $canSplit = false)
    {
        $prefix = $name ? $name .': ' : '';
        $suffix = $code ? ' #'. $code : '';

        $secondRoom = $maxLength - mb_strlen($suffix);
        $firstRoom = $secondRoom - mb_strlen($prefix);

        if (mb_strlen($answer) <= $firstRoom) {
            $this->first = $prefix . $answer . $suffix;
            $this->storedAnswer = $answer;
            return;
        }

        $start = $this->cut($answer, $firstRoom);
        $this->first = $prefix . $start . self::ELLIPSIS . $suffix;
        $this->storedAnswer = $start;

        if ($canSplit) {
            $rest = mb_substr($answer, mb_strlen($start));
            $shownRest = $rest;

            if (mb_strlen($rest) > $secondRoom) {
                $shownRest = $this->cut($rest, $secondRoom);
                $rest = $shownRest . self::ELLIPSIS;
            }

            $this->second = $rest . $suffix;
            $this->storedAnswer .= $shownRest;
        }
    }

    public function first(): string
    {
        return $this->first;
    }

    public function second(): ?string
    {
        return $this->second;
    }

    /**
     * The answer as viewers see it over both messages, without name and code, to store in the conversation
     */
    public function storedAnswer(): string
    {
        return $this->storedAnswer;
    }

    /**
     * Cuts the text so the ellipsis still fits
     */
    private function cut(string $text, int $room): string
    {
        return mb_substr($text, 0, max(0, $room - mb_strlen(self::ELLIPSIS)));
    }
}
