<?php

namespace Studio\Totem\Tests\Feature;

use Studio\Totem\Tests\TestCase;

class TaskRequestTest extends TestCase
{
    private function validPayload(): array
    {
        return [
            'description' => 'Run cache:clear',
            'command' => 'cache:clear',
            'type' => 'expression',
            'expression' => '* * * * *',
        ];
    }

    public function test_description_is_required(): void
    {
        $this->signIn();

        $this->post(route('totem.task.create'), array_merge($this->validPayload(), ['description' => '']))
            ->assertSessionHasErrors('description');
    }

    public function test_command_is_required(): void
    {
        $this->signIn();

        $this->post(route('totem.task.create'), array_merge($this->validPayload(), ['command' => '']))
            ->assertSessionHasErrors('command');
    }

    public function test_expression_required_when_type_is_expression(): void
    {
        $this->signIn();

        $this->post(route('totem.task.create'), array_merge($this->validPayload(), ['expression' => '']))
            ->assertSessionHasErrors('expression');
    }

    public function test_invalid_cron_expression_fails(): void
    {
        $this->signIn();

        $this->post(route('totem.task.create'), array_merge($this->validPayload(), ['expression' => 'not-a-cron']))
            ->assertSessionHasErrors('expression');
    }

    public function test_frequencies_required_when_type_is_frequency(): void
    {
        $this->signIn();

        $this->post(route('totem.task.create'), [
            'description' => 'Run cache:clear',
            'command' => 'cache:clear',
            'type' => 'frequency',
        ])->assertSessionHasErrors('frequencies');
    }

    public function test_invalid_notification_email_fails(): void
    {
        $this->signIn();

        $this->post(route('totem.task.create'), array_merge($this->validPayload(), [
            'notification_email_address' => 'not-an-email',
        ]))->assertSessionHasErrors('notification_email_address');
    }

    public function test_notification_phone_too_short_fails(): void
    {
        $this->signIn();

        $this->post(route('totem.task.create'), array_merge($this->validPayload(), [
            'notification_phone_number' => '1234567890', // 10 digits — below minimum of 11
        ]))->assertSessionHasErrors('notification_phone_number');
    }
}
