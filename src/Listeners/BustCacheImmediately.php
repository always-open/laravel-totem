<?php

namespace Studio\Totem\Listeners;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Cache;
use Studio\Totem\Events\Event;

class BustCacheImmediately
{
    /**
     * @var Container
     */
    protected Container $app;

    /**
     * Create the event listener.
     *
     * @param  Container  $app
     */
    public function __construct(Container $app)
    {
        $this->app = $app;
    }

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

        if ($event->taskId) {
            $cache->forget('totem.task.'.$event->taskId);
        }

        $cache->forget('totem.tasks.all');
        $cache->forget('totem.tasks.active');
    }
}
