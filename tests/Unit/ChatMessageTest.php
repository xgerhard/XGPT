<?php

namespace Tests\Unit;

use App\Services\ChatMessage;
use PHPUnit\Framework\TestCase;

class ChatMessageTest extends TestCase
{
    public function test_short_answer_is_one_message(): void
    {
        $message = new ChatMessage('hello', 399, 'Bob', 'abc', true);

        $this->assertSame('Bob: hello #abc', $message->first());
        $this->assertNull($message->second());
        $this->assertSame('hello', $message->shownAnswer());
        $this->assertSame('hello', (new ChatMessage('hello', 399))->first());
    }

    public function test_answer_that_exactly_fits_is_not_cut(): void
    {
        $fits = str_repeat('x', 20 - strlen('Bob: ') - strlen(' #abc'));

        $this->assertNull((new ChatMessage($fits, 20, 'Bob', 'abc', true))->second());
        $this->assertNotNull((new ChatMessage($fits.'x', 20, 'Bob', 'abc', true))->second());
    }

    public function test_long_answer_continues_in_the_second_message(): void
    {
        $message = new ChatMessage('abcdefghijklmnopqrst', 20, 'Bob', 'abc', true);

        $this->assertSame('Bob: abcdefg... #abc', $message->first());
        $this->assertSame('hijklmnopqrst #abc', $message->second());
        $this->assertSame('abcdefghijklmnopqrst', $message->shownAnswer());
    }

    public function test_both_messages_stay_within_the_limit(): void
    {
        foreach (['x', 'न', '😂', 'é'] as $character) {
            foreach ([199, 399] as $limit) {
                $message = new ChatMessage(str_repeat($character, 2000), $limit, '[❤️] Bob', 'abc', true);

                $this->assertSame($limit, mb_strlen($message->first()));
                $this->assertSame($limit, mb_strlen($message->second()));
                $this->assertStringEndsWith('... #abc', $message->second());
                $this->assertTrue(mb_check_encoding($message->first().$message->second(), 'UTF-8'));
                $this->assertSame(2 * $limit - mb_strlen('[❤️] Bob: ') - 2 * mb_strlen('... #abc'), mb_strlen($message->shownAnswer()));
            }
        }
    }

    public function test_long_answer_is_cut_when_there_is_no_second_message(): void
    {
        $message = new ChatMessage('abcdefghijklmnopqrst', 20, 'Bob', 'abc');

        $this->assertSame('Bob: abcdefg... #abc', $message->first());
        $this->assertNull($message->second());
        $this->assertSame('abcdefg', $message->shownAnswer());
    }
}
