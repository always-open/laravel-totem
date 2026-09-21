<?php

namespace Studio\Totem\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Studio\Totem\Console\Commands\ListSchedule;
use Studio\Totem\Task;
use Studio\Totem\Tests\TestCase;
use Studio\Totem\Totem;

class NextRunDateTest extends TestCase
{
    /**
     * Syntactically valid, but no calendar date satisfies it.
     */
    private const IMPOSSIBLE = '0 0 31 2 *';

    public function test_next_run_date_resolves_a_matching_expression(): void
    {
        Carbon::setTestNow('2026-09-18 10:15:00');

        $next = Totem::nextRunDate('30 10 * * *', Carbon::now());

        $this->assertInstanceOf(Carbon::class, $next);
        $this->assertSame('2026-09-18 10:30:00', $next->format('Y-m-d H:i:s'));
    }

    public function test_next_run_date_is_null_for_an_impossible_expression(): void
    {
        Log::shouldReceive('warning')->never();

        $this->assertNull(Totem::nextRunDate(self::IMPOSSIBLE));
    }

    public function test_next_run_date_is_null_for_a_nearest_weekday_the_month_cannot_hold(): void
    {
        Carbon::setTestNow('2029-02-10 00:00:00');

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $message) => str_contains($message, '0 0 31W * *'));

        $this->assertNull(Totem::nextRunDate('0 0 31W * *', Carbon::now()));
    }

    public function test_next_run_date_still_rejects_a_malformed_expression(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Totem::nextRunDate('not a cron');
    }

    public function test_task_upcoming_is_null_for_an_impossible_expression(): void
    {
        $task = Task::factory()->create(['expression' => self::IMPOSSIBLE]);

        $this->assertNull($task->upcoming);
        $this->assertNull($task->toArray()['upcoming']);
    }

    public function test_schedule_list_reports_never_for_an_impossible_expression(): void
    {
        $schedule = $this->app->make(Schedule::class);
        $schedule->command('inspire')->cron(self::IMPOSSIBLE)->description('Parked task');

        $this->app->make(Kernel::class)->registerCommand(new ListSchedule($schedule));

        $this->assertSame(0, Artisan::call('schedule:list'));

        $output = Artisan::output();
        $this->assertStringContainsString('Parked task', $output);
        $this->assertStringContainsString('Never', $output);
    }
}
