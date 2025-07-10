<?php

namespace Studio\Totem\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Studio\Totem\Events\Created;
use Studio\Totem\Events\Creating;
use Studio\Totem\Task;
use Studio\Totem\Tests\TestCase;

class ImportTasksTest extends TestCase
{
    public function test_it_imports_tasks_from_a_json_file()
    {
        Event::fake();

        $this->assertEquals(0, Task::count());

        $this->signIn()
            ->post(route('totem.tasks.import'), [
                'tasks' => new UploadedFile(realpath(__DIR__.'/../Fixtures/tasks.json'), 'tasks.json', 'json', false, true),
            ])->assertSuccessful();

        $this->assertEquals(5, Task::count());

        collect(json_decode(file_get_contents(realpath(__DIR__.'/../Fixtures/tasks.json'))))
            ->each(function ($jsonTask) {
                $task = Task::find($jsonTask->id);

                $this->assertEquals($jsonTask->description, $task->description);
                $this->assertEquals($jsonTask->command, $task->command);
                $this->assertEquals($jsonTask->parameters, $task->parameters);
                $this->assertEquals($jsonTask->expression, $task->expression);
                $this->assertEquals($jsonTask->timezone, $task->timezone);
                $this->assertEquals($jsonTask->is_active, $task->is_active);
                $this->assertEquals($jsonTask->dont_overlap, $task->dont_overlap);
                $this->assertEquals($jsonTask->run_in_maintenance, $task->run_in_maintenance);
                $this->assertEquals($jsonTask->notification_email_address, $task->notification_email_address);
                $this->assertEquals($jsonTask->notification_phone_number, $task->notification_phone_number);
                $this->assertEquals($jsonTask->notification_slack_webhook, $task->notification_slack_webhook);
                $this->assertEquals($jsonTask->auto_cleanup_num, $task->auto_cleanup_num);
                $this->assertEquals($jsonTask->auto_cleanup_type, $task->auto_cleanup_type);
                $this->assertEquals($jsonTask->run_on_one_server, $task->run_on_one_server);
                $this->assertEquals($jsonTask->run_in_background, $task->run_in_background);
                $this->assertEquals($jsonTask->activated, $task->activated);
            });

        Event::assertDispatched(Creating::class, 5);
        Event::assertDispatched(Created::class, 5);
    }
}
