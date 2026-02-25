<?php

namespace Studio\Totem\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider;
use Studio\Totem\Events\Activated;
use Studio\Totem\Events\Created;
use Studio\Totem\Events\Deactivated;
use Studio\Totem\Events\Deleting;
use Studio\Totem\Events\Updated;
use Studio\Totem\Listeners\BuildCache;
use Studio\Totem\Listeners\BustCache;
use Studio\Totem\Listeners\BustCacheImmediately;

class TotemEventServiceProvider extends EventServiceProvider
{
    protected $listen = [
        Created::class => [BustCache::class, BuildCache::class],
        Updated::class => [BustCacheImmediately::class, BustCache::class, BuildCache::class],
        Activated::class => [BustCacheImmediately::class, BustCache::class, BuildCache::class],
        Deactivated::class => [BustCacheImmediately::class, BustCache::class, BuildCache::class],
        Deleting::class => [BustCacheImmediately::class],
    ];
}
