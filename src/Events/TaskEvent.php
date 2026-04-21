<?php

namespace Studio\Totem\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Studio\Totem\Task;

class TaskEvent extends Event
{
    use Dispatchable, SerializesModels;

    /**
     * @var Task
     */
    public Task $task;

    public int $taskId;

    public function __construct(Task $task)
    {
        $this->task = $task;
        $this->taskId = $task->id;
    }
}
