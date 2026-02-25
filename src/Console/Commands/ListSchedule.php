<?php

namespace Studio\Totem\Console\Commands;

use Carbon\Carbon;
use Cron\CronExpression;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Str;

class ListSchedule extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'schedule:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all scheduled tasks';

    /**
     * @var Schedule
     */
    private $schedule;

    /**
     * Create a new command instance.
     *
     * @param  Schedule  $schedule
     * @return void
     */
    public function __construct(Schedule $schedule)
    {
        parent::__construct();

        $this->schedule = $schedule;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if (count($this->schedule->events()) > 0) {
            $events = collect($this->schedule->events())->map(function ($event) {
                return [
                    'description' => $event->description ?: 'N/A',
                    'command' => ltrim(strtok(Str::after($event->command, "'artisan'"), ' ')),
                    'schedule' => $event->expression,
                    'upcoming' => $this->upcoming($event),
                    'timezone' => $event->timezone ?: config('app.timezone'),
                    'overlaps' => $event->withoutOverlapping ? 'No' : 'Yes',
                    'maintenance' => $event->evenInMaintenanceMode ? 'Yes' : 'No',
                    'one_server' => $event->onOneServer ? 'Yes' : 'No',
                    'in_background' => $event->runInBackground ? 'Yes' : 'No',
                ];
            });

            $this->table(
                ['Description', 'Command', 'Schedule', 'Upcoming', 'Timezone', 'Overlaps?', 'In Maintenance?', 'One Server?', 'In Background?'],
                $events
            );
        } else {
            $this->info('No Scheduled Commands Found');
        }
    }

    /**
     * Get Upcoming schedule.
     *
     * @return bool
     */
    protected function upcoming($event)
    {
        $date = Carbon::now();

        if ($event->timezone) {
            $date->setTimezone($event->timezone);
        }

        return (new CronExpression($event->expression))->getNextRunDate($date->toDateTimeString())->format('Y-m-d H:i:s');
    }
}
