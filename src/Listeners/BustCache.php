<?php

namespace Studio\Totem\Listeners;

use Illuminate\Support\Facades\Cache;
use Studio\Totem\Events\Event;

class BustCache extends Listener
{
    /**
     * Handle the event.
     *
     * @param  Event  $event
     */
    public function handle(Event $event)
    {
        $this->clear($event);
    }

    /**
     * Clear Cache.
     *
     * @param  Event  $event
     */
    protected function clear(Event $event)
    {
        $cache = Cache::store(config('totem.cache_store'));

        if ($event->task) {
            $cache->forget('totem.task.'.$event->task->id);
        }

        $cache->forget('totem.tasks.all');
        $cache->forget('totem.tasks.active');
    }
}
