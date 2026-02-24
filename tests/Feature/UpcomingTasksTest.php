<?php

namespace Studio\Totem\Tests\Feature;

use Studio\Totem\Task;
use Studio\Totem\Tests\TestCase;

class UpcomingTasksTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('app.timezone', 'UTC');
    }

    public function test_user_can_view_upcoming_page()
    {
        $this->signIn();

        $response = $this->get(route('totem.upcoming'));

        $response->assertStatus(200);
    }

    public function test_guest_cannot_view_upcoming_page()
    {
        $response = $this->get(route('totem.upcoming'));

        $response->assertStatus(403);
    }

    public function test_events_endpoint_returns_json_structure()
    {
        $this->signIn();

        $response = $this->getJson(route('totem.upcoming.events', [
            'start' => '2026-01-01T00:00:00+00:00',
            'days'  => 1,
        ]));

        $response->assertStatus(200)
            ->assertJsonStructure(['start', 'end', 'days', 'events']);
    }

    public function test_events_endpoint_includes_active_task_runs()
    {
        $this->signIn();

        $task = Task::factory()->create(['expression' => '0 8 * * *']);

        $response = $this->getJson(route('totem.upcoming.events', [
            'start' => '2026-01-01T00:00:00+00:00',
            'days'  => 1,
        ]));

        $response->assertStatus(200);

        $taskIds = collect($response->json('events'))->pluck('task_id');
        $this->assertTrue($taskIds->contains($task->id));
    }

    public function test_events_endpoint_excludes_inactive_tasks()
    {
        $this->signIn();

        $inactive = Task::factory()->create([
            'expression' => '0 8 * * *',
            'is_active'  => false,
        ]);

        $response = $this->getJson(route('totem.upcoming.events', [
            'start' => '2026-01-01T00:00:00+00:00',
            'days'  => 1,
        ]));

        $taskIds = collect($response->json('events'))->pluck('task_id');
        $this->assertFalse($taskIds->contains($inactive->id));
    }

    public function test_events_endpoint_respects_window_boundary()
    {
        $this->signIn();

        $task = Task::factory()->create(['expression' => '0 8 * * *']);

        $response = $this->getJson(route('totem.upcoming.events', [
            'start' => '2026-01-01T00:00:00+00:00',
            'days'  => 1,
        ]));

        $events = collect($response->json('events'))->where('task_id', $task->id);
        $this->assertCount(1, $events);
    }

    public function test_events_endpoint_defaults_to_one_day()
    {
        $this->signIn();

        $response = $this->getJson(route('totem.upcoming.events'));

        $response->assertStatus(200)
            ->assertJsonPath('days', 1);
    }
}
