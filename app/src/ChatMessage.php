<?php

namespace App\src;

/**
 * Turns an answer into one chat message, or two when it doesn't fit:
 * "Name: start of the answer... #abc" and "rest of the answer #abc"
 */
class ChatMessage
{
    private const ELLIPSIS = '...';

    private string $first;
    private ?string $second = null;
    private string $shownAnswer;

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
            $this->shownAnswer = $answer;
            return;
        }

        $start = $this->cut($answer, $firstRoom);
        $this->first = $prefix . $start . self::ELLIPSIS . $suffix;
        $this->shownAnswer = $start;

        if ($canSplit) {
            $rest = mb_substr($answer, mb_strlen($start));
            $shownRest = $rest;

            if (mb_strlen($rest) > $secondRoom) {
                $shownRest = $this->cut($rest, $secondRoom);
                $rest = $shownRest . self::ELLIPSIS;
            }

            $this->second = $rest . $suffix;
            $this->shownAnswer .= $shownRest;
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
     * The part of the answer that viewers get to see
     */
    public function shownAnswer(): string
    {
        return $this->shownAnswer;
    }

    /**
     * Cuts the text so the ellipsis still fits
     */
    private function cut(string $text, int $room): string
    {
        return mb_substr($text, 0, max(0, $room - mb_strlen(self::ELLIPSIS)));
    }
}
