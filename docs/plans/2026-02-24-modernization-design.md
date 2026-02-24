# Laravel Totem Modernization Design

**Date:** 2026-02-24
**Goal:** Bring every outdated pattern up to Laravel 11+ and PHP 8.2+ conventions without rewriting the architecture, targeting Taylor Otwell-level code quality.
**Approach:** Surgical modernization — targeted, auditable changes with full test coverage.

---

## Section 1: PHP/Laravel Backend

### 1.1 Route Service Provider → Inline Route Groups

**Problem:** `TotemRouteServiceProvider` extends `Illuminate\Foundation\Support\Providers\RouteServiceProvider`, which was retired in Laravel 11. The `$namespace` property and `->namespace()` chain on `Route::` are also removed.

**Solution:**
- Delete `src/Providers/TotemRouteServiceProvider.php`
- Move route registration into `TotemServiceProvider::boot()` using plain `Route::group()`:

```php
Route::prefix(config('totem.web.route_prefix', 'totem'))
    ->middleware(config('totem.web.middleware'))
    ->group(__DIR__.'/../../routes/web.php');
```

- Update `routes/web.php` from Laravel 5 string syntax to tuple syntax:

```php
// Before
Route::get('/', 'DashboardController@index')->name('totem.dashboard');

// After
Route::get('/', [DashboardController::class, 'index'])->name('totem.dashboard');
```

- Remove `mapApiRoutes()` and the empty `routes/api.php` file entirely.
- Remove `$namespace` property from route service provider entirely.
- Remove `ConsoleServiceProvider` registration as a separate class; merge its `boot()` logic into `TotemServiceProvider::boot()`.

### 1.2 Global Constants → Config

**Problem:** `TOTEM_PATH`, `TOTEM_TABLE_PREFIX`, `TOTEM_DATABASE_CONNECTION` are PHP global constants defined at runtime in `register()`. This pollutes the global namespace and makes the package fragile.

**Solution:** Remove all three `define()` calls. Read directly from config:

```php
// TotemModel — before
protected $connection = TOTEM_DATABASE_CONNECTION;

// TotemModel — after
public function getConnectionName(): ?string
{
    return config('totem.database_connection', config('database.default'));
}

public function getTable(): string
{
    $prefix = config('totem.table_prefix', '');
    $table = parent::getTable();
    return str_starts_with($table, $prefix) ? $table : $prefix.$table;
}
```

Replace all remaining `TOTEM_TABLE_PREFIX` usages in `Result.php` and `EloquentTaskRepository.php` with `config('totem.table_prefix', '')`.

Replace `TOTEM_PATH` in `defineAssetPublishing()` with `__DIR__.'/../../'`.

### 1.3 `CronExpression::factory()` → `new CronExpression()`

**Problem:** The static `factory()` method was removed from `dragonmantank/cron-expression` v3.

**Solution:** Replace both usages:

```php
// Before
CronExpression::factory($expression)->getNextRunDate()

// After
(new CronExpression($expression))->getNextRunDate()
```

Affected files: `src/Task.php` (line 75), `src/Http/Controllers/UpcomingTasksController.php` (line 51).

### 1.4 `Validator::extend()` → Custom Rule Objects

**Problem:** `Validator::extend()` is deprecated since Laravel 9 and will be removed.

**Solution:** Create two Rule classes:

**`src/Rules/CronExpression.php`**
```php
class CronExpression implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! \Cron\CronExpression::isValidExpression($value)) {
            $fail('This is not a valid cron expression.');
        }
    }
}
```

**`src/Rules/JsonFile.php`**
```php
class JsonFile implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value->getClientOriginalExtension() !== 'json') {
            $fail('The file must be a JSON file.');
        }
    }
}
```

Update `TaskRequest` and `ImportRequest` to use these rules.
Remove both `Validator::extend()` calls from `TotemServiceProvider::boot()`.

### 1.5 Nexmo → Vonage

**Problem:** `NexmoMessage` and the `nexmo` notification channel were removed from Laravel. Nexmo is now Vonage.

**Solution:**
- Add `laravel/vonage-notification-channel` to `composer.json` `suggest` (keep as optional)
- In `TaskCompleted.php`: `NexmoMessage` → `VonageMessage`, `toNexmo()` → `toVonage()`, `'nexmo'` channel string → `'vonage'`
- Update `TaskRequest` phone validation message

### 1.6 Remove `request()` from `HasFrequencies` Model Trait

**Problem:** `HasFrequencies::processData()` calls `request()` inside a model — a separation of concerns violation. Models should not know about HTTP.

**Solution:** Move `processData()` logic into `EloquentTaskRepository::import()` where the HTTP context already exists. The model trait's `afterSave()` should receive pre-processed data, not reach into the request.

The `afterSave()` model hook can remain but should not call `processData()` directly — instead it should work off `$this->frequencies` data that was set by the repository before saving.

### 1.7 Broadcasting — Modernize

**Problem:** `BroadcastingEvent` uses an old anonymous broadcasting pattern.

**Solution:** Use typed channels and `broadcastAs()` method per Laravel 11 conventions. Update `TotemEventServiceProvider` to use FQCN strings → class constants for the `$listen` array.

---

## Section 2: Frontend — Vue 3 + Vite

### 2.1 Build Tooling: laravel-elixir/gulp → Vite

**Problem:** `laravel-elixir` and `gulp@3` are incompatible with Node 20+. The build is completely broken without the workaround `webpack.config.js` added recently.

**Solution:**
- Remove `webpack.config.js` and `laravel-elixir` dependencies
- Replace `package.json` with modern tooling:

```json
{
  "private": true,
  "scripts": {
    "dev": "vite build --watch",
    "build": "vite build"
  },
  "devDependencies": {
    "@vitejs/plugin-vue": "^5.0",
    "axios": "^1.0",
    "dayjs": "^1.11",
    "uikit": "^3.0",
    "vite": "^5.0",
    "vue": "^3.4"
  }
}
```

- Create `vite.config.js` outputting `public/js/app.js` (same published path, no Blade changes needed)

### 2.2 Vue 2 → Vue 3

**Problem:** Vue 2 is EOL. Multiple breaking patterns in use: `new Vue()`, `Vue.mixin()`, `inline-template`.

**Solution:**

Replace bootstrap:
```js
// Before
new Vue({ el: '#root', components: {...} })

// After
const app = createApp({})
// register components
app.mount('#root')
```

Replace global mixin with composable:
```js
// composables/useFormatDate.js
export function useFormatDate() {
  const formatDate = (unixTime) => dayjs(unixTime * 1000)
  const readableTimestamp = (timestamp) => formatDate(timestamp).format('HH:mm:ss')
  return { formatDate, readableTimestamp }
}
```

**Components requiring significant change:**
- `TaskType.vue` — uses `inline-template` (removed in Vue 3). Must be rewritten as a proper SFC with its own `<template>` block. The parent Blade template passes data as props instead.
- All other components — convert from Options API to Composition API (`<script setup>`)

### 2.3 moment.js → Day.js

- Remove `moment` dependency (~290KB parsed)
- Add `dayjs` (~7KB parsed)
- API is near-identical for the date formatting operations used

Affected: `UpcomingCalendar.vue`, `app.js` composable.

### 2.4 Rebuild `public/js/app.js`

Run `npm run build` to produce the updated bundle. Commit the updated `public/js/app.js`.

---

## Section 3: PHP Quality and Testing

### 3.1 Return Types and PHP 8.2+ Hygiene

Add missing return types:
- `Task::autoCleanup(): void`
- `TasksController::destroy(): RedirectResponse`
- `TaskRequest::authorize(): bool`
- `TaskRequest::rules(): array`
- `TaskRequest::messages(): array`
- `TaskRequest::validationData(): array`
- `Authenticate::handle(): mixed`

Remove PHPDoc `@param`/`@return` blocks that are made redundant by PHP 8.x type signatures (Taylor's style: type hints in signatures are the documentation).

Replace `\Exception` with `\Throwable` in catch blocks where appropriate.

### 3.2 `helpers.php` — Remove Font Awesome Dependency

`columnSort()` outputs `<span class="fa fa-caret-up">` which hardcodes Font Awesome (not shipped with the package). Replace with UIKit icon syntax that's already available:

```php
$icon = '<span uk-icon="icon: triangle-up"></span>';
```

### 3.3 `src/User.php` — Move to Tests

The `User` model in `src/` exists solely for tests and can conflict with host app models. Move to `tests/` and update `TestCase` accordingly. Do not auto-load it from the production `src/` path.

### 3.4 Remove Empty API Route File

- Delete `routes/api.php` (empty file)
- Remove `mapApiRoutes()` from route provider
- Remove `totem.api` config key (unused)

### 3.5 Test Coverage

All existing 46 assertions must continue to pass. New tests to add:
- `Rules/CronExpressionTest.php` — valid and invalid expressions
- `Rules/JsonFileTest.php` — valid json file, non-json file
- `Feature/VonageNotificationTest.php` — verify `toVonage()` returns `VonageMessage` with correct content
- `Feature/TotemModelConnectionTest.php` — verify `getConnectionName()` reads from config, not constant
- `Feature/TotemModelTablePrefixTest.php` — verify table prefix applied from config

---

## Summary of Files Changed

| Area | Files |
|------|-------|
| Deleted | `src/Providers/TotemRouteServiceProvider.php`, `routes/api.php`, `webpack.config.js`, `src/User.php` |
| New | `src/Rules/CronExpression.php`, `src/Rules/JsonFile.php`, `vite.config.js`, `tests/TestUser.php` |
| Modified | `src/Providers/TotemServiceProvider.php`, `src/TotemModel.php`, `src/Task.php`, `src/Result.php`, `src/Repositories/EloquentTaskRepository.php`, `src/Notifications/TaskCompleted.php`, `src/Traits/HasFrequencies.php`, `src/helpers.php`, `src/Http/Controllers/*.php` (all), `src/Http/Requests/TaskRequest.php`, `src/Http/Requests/ImportRequest.php`, `src/Http/Controllers/UpcomingTasksController.php`, `routes/web.php`, `composer.json`, `package.json`, `resources/assets/js/app.js`, all Vue components |
| Untouched | Database migrations (all 14 kept as-is) |

---

## Success Criteria

- [ ] All existing 46 tests pass
- [ ] New rule/notification/model tests added and passing
- [ ] `php vendor/bin/phpunit` green on PHP 8.2, 8.3, 8.4 × Laravel 11, 12
- [ ] StyleCI passes (no formatting issues)
- [ ] `npm run build` produces `public/js/app.js` without errors
- [ ] No PHP global constants remaining (`TOTEM_*`)
- [ ] No deprecated `Validator::extend()` calls
- [ ] No `CronExpression::factory()` calls
- [ ] No `NexmoMessage` / `nexmo` channel references
- [ ] No `RouteServiceProvider` extension
- [ ] No `inline-template` usage
- [ ] No `Vue.mixin()` global mixin
- [ ] No `moment` dependency
