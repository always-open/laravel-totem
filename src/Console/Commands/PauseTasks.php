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
                            {--until= : Resume automatically at this date and time}';

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

        Pause::start($until);

        $this->info($until
            ? 'Totem tasks paused until '.$until->toDateTimeString().'.'
            : 'Totem tasks paused until resumed.');

        return self::SUCCESS;
    }
}
