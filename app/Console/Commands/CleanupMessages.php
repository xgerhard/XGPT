<?php

namespace App\Console\Commands;

use App\Models\Message;
use Illuminate\Console\Command;

class CleanupMessages extends Command
{
    protected $signature = 'xgpt:cleanup';

    protected $description = 'Purge stored chat messages past the retention window';

    public function handle(): void
    {
        $cutoff = now()->subDays((int) config('xgpt.message_retention_days'));

        $deleted = Message::where('created_at', '<', $cutoff)->delete();

        $this->info("Deleted {$deleted} message(s) older than {$cutoff->toDateTimeString()}.");
    }
}
