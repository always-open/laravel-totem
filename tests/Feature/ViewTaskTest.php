<?php

namespace Studio\Totem\Tests\Feature;

use Studio\Totem\Task;
use Studio\Totem\Tests\TestCase;

class ViewTaskTest extends TestCase
{
    public function test_user_can_view_task()
    {
        $this->signIn();
        $task = Task::factory()->create();
        $response = $this->get(route('totem.task.view', ['totemTask' => $task]));
        $response->assertStatus(200);
        $response->assertSee($task->description);
        $response->assertSee('Studio\Totem\Console\Commands\ListSchedule');
        $response->assertSee($task->expression);
    }

    public function test_guest_can_not_view_task()
    {
        $task = Task::factory()->create();
        $response = $this->get(route('totem.task.view', ['totemTask' => $task]));
        $response->assertStatus(403);
    }
}
