<?php

namespace Studio\Totem\Listeners;

use Studio\Totem\Events\Event;

class BuildCache extends Listener
{
    /**
     * Handle the event.
     */
    public function handle(Event $event)
    {
        $this->build($event);
    }

    /**
     * Rebuild Cache.
     */
    protected function build(Event $event)
    {
        if ($event->task) {
            $this->tasks->find($event->task->id);
        }
    }
}
