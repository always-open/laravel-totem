# Configurable Cache Store Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add a `TOTEM_CACHE_STORE` config option so Totem's cache can be routed to a specific Laravel cache store, solving stale-cache issues on split Redis cluster deployments.

**Architecture:** Add `cache_store` to `config/totem.php`. All cache reads/writes in `EloquentTaskRepository`, `Totem::isEnabled()`, `BustCache`, and `BustCacheImmediately` resolve their store via `Cache::store(config('totem.cache_store'))`. Passing `null` to `Cache::store()` uses Laravel's default store — no breaking change for existing users.

**Tech Stack:** Laravel Cache facade, Orchestra Testbench (PHPUnit), existing `array` cache driver for tests.

---

### Task 1: Add config key and update EloquentTaskRepository

**Files:**
- Modify: `config/totem.php`
- Modify: `src/Repositories/EloquentTaskRepository.php`
- Create: `tests/Feature/CacheStoreTest.php`

**Step 1: Write the failing test**

Create `tests/Feature/CacheStoreTest.php`:

```php
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
```

**Step 2: Run test to verify it fails**

```bash
./vendor/bin/phpunit tests/Feature/CacheStoreTest.php -v
```

Expected: FAIL — config key `totem.cache_store` doesn't exist yet.

**Step 3: Add config key**

In `config/totem.php`, add before the closing `];`:

```php
'cache_store' => env('TOTEM_CACHE_STORE', null),
```

**Step 4: Add private cache helper to EloquentTaskRepository**

At the top of `src/Repositories/EloquentTaskRepository.php`, the `Cache` facade is already imported. Add a private helper method after the `builder()` method:

```php
/**
 * Get the cache store instance configured for Totem.
 *
 * @return \Illuminate\Cache\Repository
 */
private function cache(): \Illuminate\Cache\Repository
{
    return Cache::store(config('totem.cache_store'));
}
```

**Step 5: Update all cache calls in EloquentTaskRepository**

Replace every `Cache::` call in the file with `$this->cache()->`:

In `find()`:
```php
return $this->cache()->rememberForever('totem.task.'.$id, function () use ($id) {
    return Task::query()->with('frequencies')->find($id);
});
```

In `findAll()`:
```php
return $this->cache()->rememberForever('totem.tasks.all', function () {
    return Task::query()->with('frequencies')->get();
});
```

In `findAllActive()`:
```php
return $this->cache()->rememberForever('totem.tasks.active', function () {
    return $this->findAll()->filter(function ($task) {
        return $task->is_active;
    });
});
```

In `import()`, replace the three `Cache::forget(...)` calls:
```php
$this->cache()->forget('totem.tasks.all');
$this->cache()->forget('totem.tasks.active');
// ...inside the each() closure:
$this->cache()->forget('totem.task.'.$data->id);
```

**Step 6: Run test to verify it passes**

```bash
./vendor/bin/phpunit tests/Feature/CacheStoreTest.php -v
```

Expected: PASS (all 3 tests green)

**Step 7: Run full test suite to check for regressions**

```bash
./vendor/bin/phpunit -v
```

Expected: All existing tests pass.

**Step 8: Commit**

```bash
git add config/totem.php src/Repositories/EloquentTaskRepository.php tests/Feature/CacheStoreTest.php
git commit -m "Add configurable cache store via TOTEM_CACHE_STORE"
```

---

### Task 2: Update Totem::isEnabled() and cache listeners

**Files:**
- Modify: `src/Totem.php`
- Modify: `src/Listeners/BustCache.php`
- Modify: `src/Listeners/BustCacheImmediately.php`
- Modify: `tests/Feature/CacheStoreTest.php`

**Step 1: Add tests for listeners and isEnabled**

Append these tests to `tests/Feature/CacheStoreTest.php`:

```php
public function test_bust_cache_clears_from_configured_store()
{
    config(['totem.cache_store' => 'totem_store']);

    // Prime the configured store with a known key
    Cache::store('totem_store')->forever('totem.tasks.all', 'primed');
    Cache::store('totem_store')->forever('totem.tasks.active', 'primed');

    $task = \Studio\Totem\Task::factory()->create();
    Cache::store('totem_store')->forever('totem.task.'.$task->id, 'primed');

    // Fire the event that triggers BustCacheImmediately
    \Studio\Totem\Events\Deleted::dispatch($task->id);

    $this->assertFalse(Cache::store('totem_store')->has('totem.tasks.all'));
    $this->assertFalse(Cache::store('totem_store')->has('totem.tasks.active'));
}

public function test_is_enabled_uses_configured_cache_store()
{
    config(['totem.cache_store' => 'totem_store']);

    // Ensure no prior cache state
    Cache::store('totem_store')->forget('totem.table.tasks');

    \Studio\Totem\Totem::isEnabled();

    $this->assertTrue(Cache::store('totem_store')->has('totem.table.tasks'));
    $this->assertFalse(Cache::store('array')->has('totem.table.tasks'));
}
```

**Step 2: Run tests to verify they fail**

```bash
./vendor/bin/phpunit tests/Feature/CacheStoreTest.php -v
```

Expected: The two new tests FAIL.

**Step 3: Update Totem::isEnabled()**

In `src/Totem.php`, the `Cache` facade is already imported. Update `isEnabled()`:

```php
public static function isEnabled(): bool
{
    try {
        $cache = Cache::store(config('totem.cache_store'));

        if ($cache->get('totem.table.'.TOTEM_TABLE_PREFIX.'tasks')) {
            return true;
        }

        if (Schema::hasTable(TOTEM_TABLE_PREFIX.'tasks')) {
            $cache->forever('totem.table.'.TOTEM_TABLE_PREFIX.'tasks', true);

            return true;
        }
    } catch (Throwable $e) {
        return false;
    }

    return false;
}
```

**Step 4: Update BustCache**

In `src/Listeners/BustCache.php`, add the `Cache` facade import at the top:

```php
use Illuminate\Support\Facades\Cache;
```

Replace the `clear()` method body:

```php
protected function clear(Event $event)
{
    $cache = Cache::store(config('totem.cache_store'));

    if ($event->task) {
        $cache->forget('totem.task.'.$event->task->id);
    }

    $cache->forget('totem.tasks.all');
    $cache->forget('totem.tasks.active');
}
```

Note: Remove the two `$this->app['cache']->forget(...)` calls.

**Step 5: Update BustCacheImmediately**

In `src/Listeners/BustCacheImmediately.php`, add the `Cache` facade import:

```php
use Illuminate\Support\Facades\Cache;
```

Replace the `clear()` method body:

```php
protected function clear(Event $event)
{
    $cache = Cache::store(config('totem.cache_store'));

    if ($event->taskId) {
        $cache->forget('totem.task.'.$event->taskId);
    }

    $cache->forget('totem.tasks.all');
    $cache->forget('totem.tasks.active');
}
```

**Step 6: Run tests to verify they pass**

```bash
./vendor/bin/phpunit tests/Feature/CacheStoreTest.php -v
```

Expected: All 5 tests PASS.

**Step 7: Run full suite**

```bash
./vendor/bin/phpunit -v
```

Expected: All tests pass.

**Step 8: Commit**

```bash
git add src/Totem.php src/Listeners/BustCache.php src/Listeners/BustCacheImmediately.php tests/Feature/CacheStoreTest.php
git commit -m "Route Totem cache through configured store in listeners and isEnabled"
```

---

### Task 3: Update README

**Files:**
- Modify: `README.md`

**Step 1: Add Cache Store section to README**

In `README.md`, add a new subsection after the `##### Table Prefix` section (line 57) and before `#### Updating`:

```markdown
##### Cache Store

By default Totem uses your application's default cache store. In environments where UI servers and background worker servers use separate cache clusters (e.g. different Redis instances), Totem's cache can become inconsistent — a bust event on one server won't clear the cache on the other.

Set `TOTEM_CACHE_STORE` to a named store from your `config/cache.php` that is accessible by all servers:

```
TOTEM_CACHE_STORE=redis-shared
```

Setting it to `array` disables cache persistence entirely (each request hits the database):

```
TOTEM_CACHE_STORE=array
```

Leaving it unset uses your application's default cache store (existing behaviour).
```

**Step 2: Commit**

```bash
git add README.md
git commit -m "Document TOTEM_CACHE_STORE configuration option"
```
