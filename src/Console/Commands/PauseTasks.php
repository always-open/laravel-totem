<?php

namespace Studio\Totem\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Studio\Totem\Pause;
use Throwable;

class PauseTasks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'totem:pause
                            {--until= : Resume automatically at this date and time, in the app timezone unless one is given}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pause the scheduled runs of all Totem tasks';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $until = null;

        if ($this->option('until')) {
            try {
                $until = Carbon::parse($this->option('until'));
            } catch (Throwable $e) {
                $this->error('The --until option is not a valid date and time.');

                return self::FAILURE;
            }

            if ($until->lte(Carbon::now())) {
                $this->error('The --until option must be in the future.');

                return self::FAILURE;
            }
        }

        $pause = Pause::start($until);

        $this->info($pause->resume_at
            ? 'Totem tasks paused until '.$pause->resume_at->format('Y-m-d H:i:s T').'.'
            : 'Totem tasks paused until resumed.');

        return self::SUCCESS;
    }
}
