<?php

namespace Studio\Totem\Events;

use Studio\Totem\Notifications\TaskCompleted;
use Studio\Totem\Task;

class Executed extends BroadcastingEvent
{
    /**
     * Executed constructor.
     *
     * @param  string|float|int  $started
     * @param  $output
     */
    public function __construct(Task $task, $started, $output)
    {
        parent::__construct($task);

        $time_elapsed_secs = microtime(true) - $started;

        $task->results()->create([
            'duration' => $time_elapsed_secs * 1000,
            'result' => $output,
        ]);

        $task->notify(new TaskCompleted($output));
        $task->autoCleanup();
    }
}
