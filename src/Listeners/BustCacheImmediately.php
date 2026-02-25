<?php

namespace Studio\Totem\Listeners;

use Illuminate\Support\Facades\Cache;
use Studio\Totem\Events\Event;

class BustCacheImmediately
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

        $taskId = $event->taskId ?? ($event->task->id ?? null);

        if ($taskId) {
            $cache->forget('totem.task.'.$taskId);
        }

        $cache->forget('totem.tasks.all');
        $cache->forget('totem.tasks.active');
    }
}
