<?php

namespace Studio\Totem\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Studio\Totem\Repositories\EloquentTaskRepository;
use Studio\Totem\Tests\TestCase;

class CacheStoreTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        // Configure a second named array store so we can tell them apart
        $app['config']->set('cache.stores.totem_store', [
            'driver' => 'array',
        ]);
    }

    public function test_findAll_uses_configured_cache_store()
    {
        config(['totem.cache_store' => 'totem_store']);

        $repo = new EloquentTaskRepository();
        $repo->findAll();

        $this->assertTrue(Cache::store('totem_store')->has('totem.tasks.all'));
        $this->assertFalse(Cache::store('array')->has('totem.tasks.all'));
    }

    public function test_findAll_uses_default_store_when_cache_store_is_null()
    {
        config(['totem.cache_store' => null]);

        $repo = new EloquentTaskRepository();
        $repo->findAll();

        // Default store in test env is 'array'
        $this->assertTrue(Cache::store('array')->has('totem.tasks.all'));
    }

    public function test_find_uses_configured_cache_store()
    {
        config(['totem.cache_store' => 'totem_store']);

        $task = \Studio\Totem\Task::factory()->create();
        $repo = new EloquentTaskRepository();
        $repo->find($task->id);

        $this->assertTrue(Cache::store('totem_store')->has('totem.task.'.$task->id));
        $this->assertFalse(Cache::store('array')->has('totem.task.'.$task->id));
    }

    public function test_bust_cache_clears_from_configured_store()
    {
        config(['totem.cache_store' => 'totem_store']);

        Cache::store('totem_store')->forever('totem.tasks.all', 'primed');
        Cache::store('totem_store')->forever('totem.tasks.active', 'primed');

        $task = \Studio\Totem\Task::factory()->create();
        Cache::store('totem_store')->forever('totem.task.'.$task->id, 'primed');

        \Studio\Totem\Events\Deleting::dispatch($task->id);

        $this->assertFalse(Cache::store('totem_store')->has('totem.tasks.all'));
        $this->assertFalse(Cache::store('totem_store')->has('totem.tasks.active'));
    }

    public function test_is_enabled_uses_configured_cache_store()
    {
        config(['totem.cache_store' => 'totem_store']);

        Cache::store('totem_store')->forget('totem.table.tasks');

        \Studio\Totem\Totem::isEnabled();

        $this->assertTrue(Cache::store('totem_store')->has('totem.table.tasks'));
        $this->assertFalse(Cache::store('array')->has('totem.table.tasks'));
    }
}
