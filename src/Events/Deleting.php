<?php

namespace Studio\Totem\Events;

class Deleting extends Event
{
    public $taskId;

    public function __construct($taskId)
    {
        $this->taskId = $taskId;
    }
}
