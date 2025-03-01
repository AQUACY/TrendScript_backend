<?php

namespace App\Console\Commands;

use App\Models\Content;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ArchiveFreeUserContent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'content:archive-free';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Archive content for free users that is older than a week';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting to archive content for free users...');

        // Get all free users
        $freeUsers = User::where('subscription_status', 'free')->get();
        $count = 0;

        foreach ($freeUsers as $user) {
            // Get active content older than a week
            $oldContent = Content::where('user_id', $user->id)
                ->where('status', 'active')
                ->where('created_at', '<', now()->subWeek())
                ->get();

            foreach ($oldContent as $content) {
                $content->archive();
                $count++;
            }
        }

        $this->info("Archived {$count} content items for free users.");
        Log::info("Archived {$count} content items for free users.");

        return Command::SUCCESS;
    }
}
