<?php

namespace Studio\Totem\Tests\Feature;

use Studio\Totem\Task;
use Studio\Totem\Tests\TestCase;

class TotemModelTest extends TestCase
{
    public function test_model_uses_configured_table_prefix(): void
    {
        config(['totem.table_prefix' => 'custom_']);

        $task = new Task;
        $this->assertEquals('custom_tasks', $task->getTable());
    }

    public function test_model_uses_empty_prefix_by_default(): void
    {
        config(['totem.table_prefix' => '']);

        $task = new Task;
        $this->assertEquals('tasks', $task->getTable());
    }

    public function test_model_uses_configured_database_connection(): void
    {
        config(['totem.database_connection' => 'sqlite']);

        $task = new Task;
        $this->assertEquals('sqlite', $task->getConnectionName());
    }

    public function test_model_falls_back_to_default_connection_when_not_configured(): void
    {
        config(['totem.database_connection' => null]);

        $task = new Task;
        $this->assertEquals(config('database.default'), $task->getConnectionName());
    }
}
