<?php

namespace Studio\Totem\Console\Commands;

use Illuminate\Console\Command;
use Studio\Totem\Pause;

class ResumeTasks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'totem:resume';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Resume the scheduled runs of all Totem tasks';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! Pause::end()) {
            $this->info('Totem tasks are not paused.');

            return self::SUCCESS;
        }

        $this->info('Totem tasks resumed.');

        return self::SUCCESS;
    }
}
