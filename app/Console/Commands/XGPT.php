<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Conversation;

class XGPT extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xgpt:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup expired conversations';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        Conversation::where('created_at', '<', now()->subDay())->delete();
    }
}
