<?php

namespace Studio\Totem\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Studio\Totem\Pause;
use Studio\Totem\Providers\TotemServiceProvider;
use Studio\Totem\Result;
use Studio\Totem\Task;
use Studio\Totem\Tests\TestCase;

class PauseTasksTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * Register Totem's tasks on a fresh scheduler and return its events.
     */
    private function scheduledEvents(): array
    {
        $scheduler = new Schedule;

        $this->app->resolveProvider(TotemServiceProvider::class)
            ->scheduleTotemTasks($scheduler);

        return $scheduler->events();
    }

    public function test_guest_cannot_pause_or_resume(): void
    {
        $this->post(route('totem.tasks.pause'))->assertForbidden();
        $this->delete(route('totem.tasks.resume'))->assertForbidden();

        $this->assertNull(Pause::current());
    }

    public function test_user_can_pause_until_resumed(): void
    {
        $this->signIn();

        $this->post(route('totem.tasks.pause'))
            ->assertRedirect(route('totem.tasks.all'))
            ->assertSessionHas('success');

        $pause = Pause::current();
        $this->assertNotNull($pause);
        $this->assertNull($pause->resume_at);
    }

    public function test_pause_with_duration_ends_on_its_own(): void
    {
        Carbon::setTestNow('2026-10-07 12:00:00');
        $this->signIn();

        $this->post(route('totem.tasks.pause'), ['duration' => 60]);

        $this->assertEquals('2026-10-07 13:00:00', Pause::current()->resume_at->toDateTimeString());

        Carbon::setTestNow('2026-10-07 12:59:59');
        $this->assertNotNull(Pause::current());

        Carbon::setTestNow('2026-10-07 13:00:00');
        $this->assertNull(Pause::current());
    }

    public function test_pause_until_a_given_time(): void
    {
        Carbon::setTestNow('2026-10-07 12:00:00');
        $this->signIn();

        $this->post(route('totem.tasks.pause'), ['until' => '2026-10-08T09:30']);

        $this->assertEquals('2026-10-08 09:30:00', Pause::current()->resume_at->toDateTimeString());
    }

    public function test_pausing_again_replaces_the_resume_time(): void
    {
        $this->signIn();

        $this->post(route('totem.tasks.pause'), ['duration' => 15]);
        $this->post(route('totem.tasks.pause'));

        $this->assertEquals(1, Pause::count());
        $this->assertNull(Pause::current()->resume_at);
    }

    public function test_user_can_resume(): void
    {
        $this->signIn();
        Pause::start();

        $this->delete(route('totem.tasks.resume'))
            ->assertRedirect(route('totem.tasks.all'))
            ->assertSessionHas('success');

        $this->assertNull(Pause::current());
        $this->assertNotNull(Pause::first()->resumed_at);
    }

    public function test_pause_validation(): void
    {
        $this->signIn();

        $this->post(route('totem.tasks.pause'), ['until' => Carbon::now()->subHour()->toDateTimeString()])
            ->assertSessionHasErrors('until');

        $this->post(route('totem.tasks.pause'), ['duration' => 7])
            ->assertSessionHasErrors('duration');

        $this->post(route('totem.tasks.pause'), [
            'duration' => 60,
            'until' => Carbon::now()->addDay()->toDateTimeString(),
        ])->assertSessionHasErrors('duration');

        $this->assertNull(Pause::current());
    }

    public function test_pausing_leaves_task_activation_untouched(): void
    {
        $active = Task::factory()->create(['is_active' => true]);
        $inactive = Task::factory()->create(['is_active' => false]);

        Pause::start();
        Pause::end();

        $this->assertTrue((bool) $active->fresh()->is_active);
        $this->assertFalse((bool) $inactive->fresh()->is_active);
    }

    public function test_scheduler_skips_tasks_while_paused(): void
    {
        Carbon::setTestNow('2026-10-07 12:00:00');
        Task::factory()->count(2)->create();

        foreach ($this->scheduledEvents() as $event) {
            $this->assertTrue($event->filtersPass($this->app));
        }

        Pause::start(Carbon::now()->addHour());
        $events = $this->scheduledEvents();

        $this->assertCount(2, $events);
        foreach ($events as $event) {
            $this->assertFalse($event->filtersPass($this->app));
        }

        Carbon::setTestNow('2026-10-07 13:00:00');
        foreach ($events as $event) {
            $this->assertTrue($event->filtersPass($this->app));
        }
    }

    public function test_scheduler_runs_tasks_after_resume(): void
    {
        Task::factory()->create();
        Pause::start();
        Pause::end();

        foreach ($this->scheduledEvents() as $event) {
            $this->assertTrue($event->filtersPass($this->app));
        }
    }

    public function test_manual_execution_still_works_while_paused(): void
    {
        $this->signIn();
        $task = Task::factory()->create();
        Pause::start();

        $this->get(route('totem.task.execute', $task))->assertOk();

        $this->assertEquals(1, Result::count());
    }

    public function test_task_list_shows_pause_state(): void
    {
        $this->signIn();

        $this->get(route('totem.tasks.all'))
            ->assertSee('Pause All')
            ->assertDontSee('All scheduled tasks are paused');

        Pause::start();

        $this->get(route('totem.tasks.all'))
            ->assertSee('All scheduled tasks are paused')
            ->assertSee('until resumed manually')
            ->assertDontSee('Pause All');
    }

    public function test_pause_command(): void
    {
        Carbon::setTestNow('2026-10-07 12:00:00');

        $this->artisan('totem:pause', ['--until' => '2026-10-09 08:00'])->assertSuccessful();
        $this->assertEquals('2026-10-09 08:00:00', Pause::current()->resume_at->toDateTimeString());

        $this->artisan('totem:pause')->assertSuccessful();
        $this->assertNull(Pause::current()->resume_at);
    }

    public function test_pause_command_rejects_bad_input(): void
    {
        $this->artisan('totem:pause', ['--until' => 'not a date'])->assertFailed();
        $this->artisan('totem:pause', ['--until' => Carbon::now()->subDay()->toDateTimeString()])->assertFailed();

        $this->assertNull(Pause::current());
    }

    public function test_resume_command(): void
    {
        $this->artisan('totem:resume')
            ->expectsOutput('Totem tasks are not paused.')
            ->assertSuccessful();

        Pause::start();

        $this->artisan('totem:resume')
            ->expectsOutput('Totem tasks resumed.')
            ->assertSuccessful();

        $this->assertNull(Pause::current());
    }
}
