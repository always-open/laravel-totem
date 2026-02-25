<?php

namespace Studio\Totem\Tests\Feature;

use Studio\Totem\Notifications\TaskCompleted;
use Studio\Totem\Task;
use Studio\Totem\Tests\TestCase;

class VonageNotificationTest extends TestCase
{
    public function test_vonage_channel_used_when_phone_number_set(): void
    {
        $task = Task::factory()->create(['notification_phone_number' => '18001234567']);
        $notification = new TaskCompleted('Task output here');

        $channels = $notification->via($task);

        $this->assertContains('vonage', $channels);
        $this->assertNotContains('nexmo', $channels);
    }

    public function test_no_vonage_channel_when_no_phone_number(): void
    {
        $task = Task::factory()->create(['notification_phone_number' => null]);
        $notification = new TaskCompleted('output');

        $channels = $notification->via($task);

        $this->assertNotContains('vonage', $channels);
        $this->assertNotContains('nexmo', $channels);
    }

    public function test_to_vonage_returns_correct_content(): void
    {
        $task = Task::factory()->create(['description' => 'My Task', 'notification_phone_number' => '18001234567']);
        $notification = new TaskCompleted('output');

        $message = $notification->toVonage($task);

        $this->assertStringContainsString('My Task', $message->content);
    }
}
