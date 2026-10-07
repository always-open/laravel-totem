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
                            {--for= : Resume automatically after this long, e.g. 30m, 2h or 1d}
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
        if ($this->option('for') && $this->option('until')) {
            $this->error('Use either --for or --until, not both.');

            return self::FAILURE;
        }

        $until = null;

        if ($for = $this->option('for')) {
            if (! preg_match('/^(\d+)([mhd])$/', $for, $matches) || (int) $matches[1] === 0) {
                $this->error('The --for option must look like 30m, 2h or 1d.');

                return self::FAILURE;
            }

            $until = match ($matches[2]) {
                'm' => Carbon::now()->addMinutes((int) $matches[1]),
                'h' => Carbon::now()->addHours((int) $matches[1]),
                'd' => Carbon::now()->addDays((int) $matches[1]),
            };
        }

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
