<?php

namespace App\Chat\Contracts;

use App\Chat\IncomingMessage;
use Illuminate\Http\Request;

interface BotProvider
{
    /**
     * A short, stable identifier for this bot integration, e.g. 'nightbot'.
     */
    public function key(): string;

    /**
     * Does this request look like it came from this bot? Called in registration order by
     * App\Chat\BotProviderRegistry until one claims it.
     */
    public function matches(Request $request): bool;

    /**
     * Normalize a matched request into a bot-agnostic DTO.
     */
    public function parse(Request $request): IncomingMessage;
}
