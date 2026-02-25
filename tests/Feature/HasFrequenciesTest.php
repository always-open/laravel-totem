<?php

namespace Studio\Totem\Tests\Feature;

use Studio\Totem\Task;
use Studio\Totem\Tests\TestCase;

class HasFrequenciesTest extends TestCase
{
    public function test_frequencies_are_created_when_type_is_frequency(): void
    {
        $task = Task::factory()->create(['expression' => null]);

        $task->afterSave([
            'type' => 'frequency',
            'frequencies' => [
                ['interval' => 'everyMinute', 'label' => 'Every Minute'],
            ],
        ]);

        $this->assertDatabaseHas('task_frequencies', [
            'task_id' => $task->id,
            'interval' => 'everyMinute',
            'label' => 'Every Minute',
        ]);
    }

    public function test_removed_frequency_is_deleted_on_update(): void
    {
        $task = Task::factory()->create(['expression' => null]);

        $task->afterSave([
            'type' => 'frequency',
            'frequencies' => [
                ['interval' => 'everyMinute', 'label' => 'Every Minute'],
                ['interval' => 'hourly', 'label' => 'Hourly'],
            ],
        ]);

        $task->load('frequencies');

        $task->afterSave([
            'type' => 'frequency',
            'frequencies' => [
                ['interval' => 'everyMinute', 'label' => 'Every Minute'],
            ],
        ]);

        $this->assertDatabaseHas('task_frequencies', [
            'task_id' => $task->id,
            'interval' => 'everyMinute',
        ]);
        $this->assertDatabaseMissing('task_frequencies', [
            'task_id' => $task->id,
            'interval' => 'hourly',
        ]);
    }

    public function test_frequencies_deleted_when_type_changes_to_expression(): void
    {
        $task = Task::factory()->create(['expression' => null]);

        $task->afterSave([
            'type' => 'frequency',
            'frequencies' => [
                ['interval' => 'everyMinute', 'label' => 'Every Minute'],
            ],
        ]);

        $task->load('frequencies');

        $task->afterSave(['type' => 'expression']);

        $this->assertDatabaseMissing('task_frequencies', ['task_id' => $task->id]);
    }
}
