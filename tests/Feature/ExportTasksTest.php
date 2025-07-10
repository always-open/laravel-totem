<?php

namespace Studio\Totem\Tests\Feature;

use Carbon\Carbon;
use Studio\Totem\Task;
use Studio\Totem\Tests\TestCase;

class ExportTasksTest extends TestCase
{
    public function test_it_exports_tasks_as_json()
    {
        $tasks = Task::factory()->count(5)->create();

        $exportedTasks = $this->signIn()
            ->get(route('totem.tasks.export'))
            ->assertHeader('Content-Disposition', 'attachment; filename=tasks.json')
            ->assertHeader('Content-Type', 'text/json; charset=UTF-8')
            ->streamedContent();

        $exportedTasks = json_decode($exportedTasks);

        $this->assertCount($tasks->count(), $exportedTasks);

        collect($exportedTasks)->each(function ($exportedTask) {
            $task = Task::find($exportedTask->id);

            $this->assertEquals($task->description, $exportedTask->description);
            $this->assertEquals($task->command, $exportedTask->command);
            $this->assertEquals($task->parameters, $exportedTask->parameters);
            $this->assertEquals($task->expression, $exportedTask->expression);
            $this->assertEquals($task->timezone, $exportedTask->timezone);
            $this->assertEquals($task->is_active, $exportedTask->is_active);
            $this->assertEquals($task->dont_overlap, $exportedTask->dont_overlap);
            $this->assertEquals($task->run_in_maintenance, $exportedTask->run_in_maintenance);
            $this->assertEquals($task->notification_email_address, $exportedTask->notification_email_address);
            $this->assertTrue($task->created_at->eq(Carbon::parse($exportedTask->created_at)));
            $this->assertTrue($task->updated_at->eq(Carbon::parse($exportedTask->updated_at)));
            $this->assertEquals($task->notification_phone_number, $exportedTask->notification_phone_number);
            $this->assertEquals($task->notification_slack_webhook, $exportedTask->notification_slack_webhook);
            $this->assertEquals($task->auto_cleanup_num, $exportedTask->auto_cleanup_num);
            $this->assertEquals($task->auto_cleanup_type, $exportedTask->auto_cleanup_type);
            $this->assertEquals($task->run_on_one_server, $exportedTask->run_on_one_server);
            $this->assertEquals($task->run_in_background, $exportedTask->run_in_background);
            $this->assertEquals($task->activated, $exportedTask->activated);
            $this->assertEquals($task->upcoming, $exportedTask->upcoming);
            $this->assertEquals($task->last_result, $exportedTask->last_result);
            $this->assertEquals($task->average_runtime, $exportedTask->average_runtime);
        });
    }
}
