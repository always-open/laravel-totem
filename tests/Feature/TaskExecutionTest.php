<?php

namespace Studio\Totem\Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Studio\Totem\Events\Executed;
use Studio\Totem\Events\Executing;
use Studio\Totem\Providers\ConsoleServiceProvider;
use Studio\Totem\Result;
use Studio\Totem\Task;
use Studio\Totem\Tests\TestCase;

class TaskExecutionTest extends TestCase
{
    public function test_it_runs_a_scheduled_task()
    {
        $task = Task::factory()->create();

        Event::fake();

        $scheduler = $this->app->get(Schedule::class);
        $this->app->resolveProvider(ConsoleServiceProvider::class)
            ->schedule($scheduler);

        $scheduler->events()[0]
            ->run($this->app);

        $this->assertEquals(1, Result::count());

        $result = Result::first();
        $this->assertEquals($task->id, $result->task_id);

        Event::assertDispatched(Executing::class);
        Event::assertDispatched(Executed::class);
    }

    public function test_it_executes_a_scheduled_task()
    {
        $task = Task::factory()->create();

        Event::fake();

        $this->signIn()
            ->get(route('totem.task.execute', ['totemTask' => $task]))
            ->assertSuccessful();

        $this->assertEquals(1, Result::count());

        $result = Result::first();
        $this->assertEquals($task->id, $result->task_id);

        Event::assertDispatched(Executed::class);
    }
}
