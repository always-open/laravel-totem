# Design: Configurable Cache Store

**Date:** 2026-02-24
**Status:** Approved

## Problem

In deployments where UI servers and background worker servers use separate Redis clusters, Totem's cache becomes inconsistent. A bust event fired by a worker server clears keys from its own Redis cluster, but the UI server's Redis still holds stale data, and vice versa. Users need a way to point Totem's cache at a shared backend — or bypass caching entirely.

## Solution

Add a `cache_store` config key that lets users specify which Laravel cache store Totem should use. Defaults to `null`, which preserves the existing behaviour (uses Laravel's default cache store).

## Design

### Config (`config/totem.php`)

```php
'cache_store' => env('TOTEM_CACHE_STORE', null),
```

`null` means "use the application's default cache store" — no behaviour change for existing installs.

### Cache Resolution

All cache calls throughout Totem use `Cache::store(config('totem.cache_store'))` instead of the `Cache` facade directly. When `cache_store` is `null`, `Cache::store(null)` resolves to the default store, so behaviour is unchanged.

### Affected Files

- `config/totem.php` — add `cache_store` key
- `src/Repositories/EloquentTaskRepository.php` — `find()`, `findAll()`, `findAllActive()`, `import()`
- `src/Totem.php` — `isEnabled()`
- `src/Listeners/BustCache.php` — `clear()`
- `src/Listeners/BustCacheImmediately.php` — `clear()`
- `README.md` — add Cache Store section under Configuration

### README Addition

Add a **Cache Store** subsection to the Configuration section:

> By default Totem uses your application's default cache store. In environments where UI servers and background worker servers use separate cache clusters, set `TOTEM_CACHE_STORE` to a named store from your `config/cache.php` that is accessible by all servers. Setting it to `array` disables cache persistence entirely.
>
> ```
> TOTEM_CACHE_STORE=redis-shared
> ```

## Non-Goals

- No `cache_enabled` boolean flag — `array` store covers the "disable" case
- No changes to cache key names or TTLs
- No changes to `BuildCache` listener (it calls `find()` which already goes through the repository)

## Usage Examples

| Scenario | Setting |
|---|---|
| Shared Redis instance | `TOTEM_CACHE_STORE=redis-shared` |
| Database-backed cache | `TOTEM_CACHE_STORE=database` |
| Disable caching | `TOTEM_CACHE_STORE=array` |
| Default (no change) | _(unset)_ |
