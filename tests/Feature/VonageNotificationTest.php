<?php

namespace Studio\Totem\Tests\Feature;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;
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

    public function test_mail_channel_used_when_email_set(): void
    {
        $task = Task::factory()->create(['notification_email_address' => 'test@example.com']);
        $notification = new TaskCompleted('output');

        $this->assertContains('mail', $notification->via($task));
    }

    public function test_slack_channel_used_when_webhook_set(): void
    {
        $task = Task::factory()->create(['notification_slack_webhook' => 'https://hooks.slack.com/test']);
        $notification = new TaskCompleted('output');

        $this->assertContains('slack', $notification->via($task));
    }

    public function test_to_mail_uses_task_description_as_subject_and_includes_output(): void
    {
        $task = Task::factory()->create(['description' => 'My Task']);
        $notification = new TaskCompleted('Task ran successfully');

        $message = $notification->toMail($task);

        $this->assertInstanceOf(MailMessage::class, $message);
        $this->assertSame('My Task', $message->subject);
        $this->assertStringContainsString('Task ran successfully', implode(' ', $message->introLines));
    }

    public function test_to_slack_includes_task_description_in_attachment(): void
    {
        $task = Task::factory()->create(['description' => 'My Task']);
        $notification = new TaskCompleted('output');

        $message = $notification->toSlack($task);

        $this->assertInstanceOf(SlackMessage::class, $message);
        $attachment = $message->attachments[0];
        $this->assertStringContainsString('My Task', $attachment->content);
    }
}
