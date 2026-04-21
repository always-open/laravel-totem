<?php

namespace Studio\Totem\Listeners;

use Illuminate\Support\Facades\Cache;
use Studio\Totem\Events\Event;

class BustCache extends Listener
{
    /**
     * Handle the event.
     */
    public function handle(Event $event)
    {
        $this->clear($event);
    }

    /**
     * Clear Cache.
     */
    protected function clear(Event $event)
    {
        $cache = Cache::store(config('totem.cache_store'));

        if (isset($event->taskId)) {
            $cache->forget('totem.task.'.$event->taskId);
        }

        $cache->forget('totem.tasks.all');
        $cache->forget('totem.tasks.active');
    }
}
