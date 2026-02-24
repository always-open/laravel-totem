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
}
