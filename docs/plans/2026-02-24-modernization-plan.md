# Laravel Totem Modernization Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Bring every outdated pattern in Laravel Totem up to Laravel 11+ / PHP 8.2+ / Vue 3 conventions without rewriting the architecture.

**Architecture:** Surgical changes only — fix deprecated APIs, remove global constants, replace dead build tooling, upgrade Vue 2 → Vue 3. Every change has a test. Migrations are untouched.

**Tech Stack:** PHP 8.2+, Laravel 11/12, Orchestra Testbench, Vue 3 Composition API, Vite 5, Day.js, UIKit 3

---

## Context: Key File Locations

- Source PHP: `src/`
- Tests: `tests/Feature/` and `tests/TestCase.php`
- Vue components: `resources/assets/js/`
- Published JS bundle: `public/js/app.js`
- Views: `resources/views/`
- Routes: `routes/web.php`
- Config: `config/totem.php`
- Run tests: `php vendor/bin/phpunit`
- Run build: `npm run build` (after Task 14)

---

## Task 1: Remove global constants — migrate `TotemModel` to config

**Why:** `TOTEM_TABLE_PREFIX` and `TOTEM_DATABASE_CONNECTION` are global PHP constants defined at runtime, which pollute the global namespace and make testing fragile.

**Files:**
- Modify: `src/TotemModel.php`
- Modify: `src/Providers/TotemServiceProvider.php`
- Test: `tests/Feature/TotemModelTest.php` (create)

**Step 1: Write the failing test**

Create `tests/Feature/TotemModelTest.php`:

```php
<?php

namespace Studio\Totem\Tests\Feature;

use Studio\Totem\Task;
use Studio\Totem\Tests\TestCase;

class TotemModelTest extends TestCase
{
    public function test_model_uses_configured_table_prefix(): void
    {
        config(['totem.table_prefix' => 'custom_']);

        $task = new Task;
        $this->assertEquals('custom_tasks', $task->getTable());
    }

    public function test_model_uses_empty_prefix_by_default(): void
    {
        config(['totem.table_prefix' => '']);

        $task = new Task;
        $this->assertEquals('tasks', $task->getTable());
    }

    public function test_model_uses_configured_database_connection(): void
    {
        config(['totem.database_connection' => 'sqlite']);

        $task = new Task;
        $this->assertEquals('sqlite', $task->getConnectionName());
    }
}
```

**Step 2: Run test to verify it fails**

```bash
php vendor/bin/phpunit tests/Feature/TotemModelTest.php
```

Expected: FAIL — `getConnectionName()` doesn't exist, and `getTable()` currently uses the constant.

**Step 3: Update `src/TotemModel.php`**

Replace the entire file with:

```php
<?php

namespace Studio\Totem;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TotemModel extends Model
{
    public function getConnectionName(): ?string
    {
        return config('totem.database_connection', config('database.default'));
    }

    public function getTable(): string
    {
        $prefix = config('totem.table_prefix', '');
        $table = parent::getTable();

        return Str::startsWith($table, $prefix) ? $table : $prefix.$table;
    }
}
```

**Step 4: Remove the three `define()` calls from `src/Providers/TotemServiceProvider.php`**

Remove these lines from `register()`:
```php
if (! defined('TOTEM_PATH')) {
    define('TOTEM_PATH', realpath(__DIR__.'/../../'));
}

if (! defined('TOTEM_TABLE_PREFIX')) {
    define('TOTEM_TABLE_PREFIX', config('totem.table_prefix'));
}

if (! defined('TOTEM_DATABASE_CONNECTION')) {
    define('TOTEM_DATABASE_CONNECTION', config('totem.database_connection', config('database.default')));
}
```

Replace `TOTEM_PATH` in `defineAssetPublishing()`:

```php
// Before
TOTEM_PATH.'/public/js' => public_path('vendor/totem/js'),

// After
__DIR__.'/../../public/js' => public_path('vendor/totem/js'),
```

Do the same for all three `TOTEM_PATH` occurrences in `defineAssetPublishing()`.

**Step 5: Replace `TOTEM_TABLE_PREFIX` in `src/Result.php`**

```php
// Before
->whereColumn('task_id', TOTEM_TABLE_PREFIX.'tasks.id')

// After (two occurrences)
->whereColumn('task_id', config('totem.table_prefix', '').'tasks.id')
```

**Step 6: Replace `TOTEM_TABLE_PREFIX` in `src/Repositories/EloquentTaskRepository.php`**

```php
// Before
return (new Task)->select(TOTEM_TABLE_PREFIX.'tasks.*')

// After
return (new Task)->select(config('totem.table_prefix', '').'tasks.*')
```

**Step 7: Run the full test suite**

```bash
php vendor/bin/phpunit
```

Expected: All 46 existing tests pass + 3 new tests pass = 49 total.

**Step 8: Commit**

```bash
git add src/TotemModel.php src/Providers/TotemServiceProvider.php src/Result.php src/Repositories/EloquentTaskRepository.php tests/Feature/TotemModelTest.php
git commit -m "refactor: replace global PHP constants with config() calls"
```

---

## Task 2: Fix deprecated `CronExpression::factory()` → `new CronExpression()`

**Why:** The static `factory()` method was removed from `dragonmantank/cron-expression` v3.

**Files:**
- Modify: `src/Task.php:75`
- Modify: `src/Http/Controllers/UpcomingTasksController.php:51`

**Step 1: Update `src/Task.php`**

```php
// Before (line 75)
return CronExpression::factory($this->getCronExpression())->getNextRunDate()->format('Y-m-d H:i:s');

// After
return (new CronExpression($this->getCronExpression()))->getNextRunDate()->format('Y-m-d H:i:s');
```

**Step 2: Update `src/Http/Controllers/UpcomingTasksController.php`**

```php
// Before (line 51)
$cron = CronExpression::factory($task->getCronExpression());

// After
$cron = new CronExpression($task->getCronExpression());
```

**Step 3: Run tests**

```bash
php vendor/bin/phpunit
```

Expected: All 49 tests pass (no regression).

**Step 4: Commit**

```bash
git add src/Task.php src/Http/Controllers/UpcomingTasksController.php
git commit -m "fix: replace deprecated CronExpression::factory() with new CronExpression()"
```

---

## Task 3: Create `CronExpression` validation rule class

**Why:** `Validator::extend('cron_expression', ...)` is deprecated. Replace with a first-class Rule object.

**Files:**
- Create: `src/Rules/CronExpression.php`
- Create: `tests/Feature/Rules/CronExpressionRuleTest.php`

**Step 1: Write the failing test**

Create `tests/Feature/Rules/CronExpressionRuleTest.php`:

```php
<?php

namespace Studio\Totem\Tests\Feature\Rules;

use Closure;
use Studio\Totem\Rules\CronExpression;
use Studio\Totem\Tests\TestCase;

class CronExpressionRuleTest extends TestCase
{
    public function test_valid_cron_expression_passes(): void
    {
        $rule = new CronExpression;
        $failed = false;

        $rule->validate('expression', '* * * * *', function () use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed);
    }

    public function test_invalid_cron_expression_fails(): void
    {
        $rule = new CronExpression;
        $message = null;

        $rule->validate('expression', 'not-a-cron', function (string $msg) use (&$message) {
            $message = $msg;
        });

        $this->assertNotNull($message);
        $this->assertStringContainsString('valid cron expression', $message);
    }

    public function test_five_part_expression_passes(): void
    {
        $rule = new CronExpression;
        $failed = false;

        $rule->validate('expression', '0 9 * * 1-5', function () use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed);
    }
}
```

**Step 2: Run test to verify it fails**

```bash
php vendor/bin/phpunit tests/Feature/Rules/CronExpressionRuleTest.php
```

Expected: FAIL — class does not exist.

**Step 3: Create `src/Rules/CronExpression.php`**

```php
<?php

namespace Studio\Totem\Rules;

use Closure;
use Cron\CronExpression as CronParser;
use Illuminate\Contracts\Validation\ValidationRule;

class CronExpression implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! CronParser::isValidExpression($value)) {
            $fail('This is not a valid cron expression.');
        }
    }
}
```

**Step 4: Run tests**

```bash
php vendor/bin/phpunit tests/Feature/Rules/CronExpressionRuleTest.php
```

Expected: 3 tests pass.

**Step 5: Run full suite**

```bash
php vendor/bin/phpunit
```

Expected: All pass.

**Step 6: Commit**

```bash
git add src/Rules/CronExpression.php tests/Feature/Rules/CronExpressionRuleTest.php
git commit -m "feat: add CronExpression validation rule class"
```

---

## Task 4: Create `JsonFile` validation rule class

**Why:** `Validator::extend('json_file', ...)` is deprecated.

**Files:**
- Create: `src/Rules/JsonFile.php`
- Create: `tests/Feature/Rules/JsonFileRuleTest.php`

**Step 1: Write the failing test**

Create `tests/Feature/Rules/JsonFileRuleTest.php`:

```php
<?php

namespace Studio\Totem\Tests\Feature\Rules;

use Illuminate\Http\UploadedFile;
use Studio\Totem\Rules\JsonFile;
use Studio\Totem\Tests\TestCase;

class JsonFileRuleTest extends TestCase
{
    public function test_json_file_passes(): void
    {
        $rule = new JsonFile;
        $file = UploadedFile::fake()->create('tasks.json', 100, 'application/json');
        $failed = false;

        $rule->validate('tasks', $file, function () use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed);
    }

    public function test_non_json_file_fails(): void
    {
        $rule = new JsonFile;
        $file = UploadedFile::fake()->create('tasks.csv', 100, 'text/csv');
        $message = null;

        $rule->validate('tasks', $file, function (string $msg) use (&$message) {
            $message = $msg;
        });

        $this->assertNotNull($message);
    }
}
```

**Step 2: Run to verify failure**

```bash
php vendor/bin/phpunit tests/Feature/Rules/JsonFileRuleTest.php
```

Expected: FAIL — class does not exist.

**Step 3: Create `src/Rules/JsonFile.php`**

```php
<?php

namespace Studio\Totem\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class JsonFile implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || $value->getClientOriginalExtension() !== 'json') {
            $fail('The :attribute must be a JSON file.');
        }
    }
}
```

**Step 4: Run tests**

```bash
php vendor/bin/phpunit tests/Feature/Rules/JsonFileRuleTest.php
```

Expected: 2 tests pass.

**Step 5: Run full suite**

```bash
php vendor/bin/phpunit
```

Expected: All pass.

**Step 6: Commit**

```bash
git add src/Rules/JsonFile.php tests/Feature/Rules/JsonFileRuleTest.php
git commit -m "feat: add JsonFile validation rule class"
```

---

## Task 5: Wire new rules into requests, remove `Validator::extend()`

**Why:** Remove the two deprecated `Validator::extend()` calls from the service provider.

**Files:**
- Modify: `src/Providers/TotemServiceProvider.php`
- Modify: `src/Http/Requests/TaskRequest.php`
- Modify: `src/Http/Requests/ImportRequest.php`

**Step 1: Update `src/Http/Requests/TaskRequest.php`**

Add the import at the top:
```php
use Studio\Totem\Rules\CronExpression;
```

Change the `rules()` method:
```php
public function authorize(): bool
{
    return true;
}

public function rules(): array
{
    return [
        'description' => ['required'],
        'command' => ['required'],
        'expression' => ['nullable', 'required_if:type,expression', new CronExpression],
        'frequencies' => ['required_if:type,frequency', 'array'],
        'notification_email_address' => ['nullable', 'email'],
        'notification_phone_number' => ['nullable', 'digits_between:11,13'],
        'notification_slack_webhook' => ['nullable', 'url'],
    ];
}

public function messages(): array
{
    return [
        'description.required' => 'Task description is required',
        'command.required' => 'Please select a command',
        'expression.required_if' => 'Cron Expression is required if task type is expression',
        'frequencies.required_if' => 'At least one frequency is required',
        'frequencies.array' => 'At least one frequency is required',
        'notification_email_address.email' => 'Email address is not valid',
        'notification_phone_number.digits_between' => 'Phone number should be between 11 and 13 digits including country code',
        'notification_slack_webhook.url' => 'Slack Webhook must be a valid url',
    ];
}

public function validationData(): array
{
    if ($this->input('type') == 'frequency') {
        $this->merge(['expression' => null]);
    }

    return $this->all();
}
```

**Step 2: Update `src/Http/Requests/ImportRequest.php`**

Read the file first to see its current content, then add the `JsonFile` rule to whatever validation it performs. If it extends `FormRequest` with a file rule, replace `'json_file'` string rule with `new JsonFile`.

Add import: `use Studio\Totem\Rules\JsonFile;`

Change any `'json_file'` string rule to `new JsonFile`.

**Step 3: Remove `Validator::extend()` calls from `src/Providers/TotemServiceProvider.php`**

Remove from `boot()`:
```php
// Remove these two blocks entirely:
Validator::extend('cron_expression', function ($attribute, $value, $parameters, $validator) {
    return CronExpression::isValidExpression($value);
});

Validator::extend('json_file', function ($attribute, UploadedFile $value, $validator) {
    return $value->getClientOriginalExtension() == 'json';
});
```

Also remove these now-unused imports from the service provider:
```php
use Cron\CronExpression;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
```

**Step 4: Run full test suite**

```bash
php vendor/bin/phpunit
```

Expected: All tests pass (the CreateTask and EditTask tests exercise validation).

**Step 5: Commit**

```bash
git add src/Providers/TotemServiceProvider.php src/Http/Requests/TaskRequest.php src/Http/Requests/ImportRequest.php
git commit -m "refactor: replace Validator::extend() with ValidationRule classes"
```

---

## Task 6: Replace Nexmo with Vonage notification channel

**Why:** `NexmoMessage` and the `nexmo` channel were removed from Laravel. Vonage is the renamed successor.

**Files:**
- Modify: `src/Notifications/TaskCompleted.php`
- Modify: `composer.json`
- Create: `tests/Feature/VonageNotificationTest.php`

**Step 1: Write the failing test**

Create `tests/Feature/VonageNotificationTest.php`:

```php
<?php

namespace Studio\Totem\Tests\Feature;

use Studio\Totem\Notifications\TaskCompleted;
use Studio\Totem\Task;
use Studio\Totem\Tests\TestCase;

class VonageNotificationTest extends TestCase
{
    public function test_vonage_channel_used_when_phone_number_set(): void
    {
        $task = Task::factory()->create(['notification_phone_number' => '18001234567']);
        $notification = new TaskCompleted('Task output here');

        $channels = $notification->via($task);

        $this->assertContains('vonage', $channels);
        $this->assertNotContains('nexmo', $channels);
    }

    public function test_no_vonage_channel_when_no_phone_number(): void
    {
        $task = Task::factory()->create(['notification_phone_number' => null]);
        $notification = new TaskCompleted('output');

        $channels = $notification->via($task);

        $this->assertNotContains('vonage', $channels);
        $this->assertNotContains('nexmo', $channels);
    }

    public function test_to_vonage_returns_correct_content(): void
    {
        $task = Task::factory()->create(['description' => 'My Task', 'notification_phone_number' => '18001234567']);
        $notification = new TaskCompleted('output');

        $message = $notification->toVonage($task);

        $this->assertStringContainsString('My Task', $message->content);
    }
}
```

**Step 2: Run to verify failure**

```bash
php vendor/bin/phpunit tests/Feature/VonageNotificationTest.php
```

Expected: FAIL — `vonage` channel not in use, `toVonage()` doesn't exist.

**Step 3: Update `src/Notifications/TaskCompleted.php`**

Replace the entire file:

```php
<?php

namespace Studio\Totem\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackAttachment;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Messages\VonageMessage;
use Illuminate\Notifications\Notification;

class TaskCompleted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $output) {}

    public function via(mixed $notifiable): array
    {
        $channels = [];

        if ($notifiable->notification_email_address) {
            $channels[] = 'mail';
        }
        if ($notifiable->notification_phone_number) {
            $channels[] = 'vonage';
        }
        if ($notifiable->notification_slack_webhook) {
            $channels[] = 'slack';
        }

        return $channels;
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($notifiable->description)
            ->greeting('Hi,')
            ->line("{$notifiable->description} just finished running.")
            ->line($this->output);
    }

    public function toVonage(mixed $notifiable): VonageMessage
    {
        return (new VonageMessage)
            ->content($notifiable->description.' just finished running.');
    }

    public function toSlack(mixed $notifiable): SlackMessage
    {
        return (new SlackMessage)
            ->content(config('app.name'))
            ->attachment(function (SlackAttachment $attachment) use ($notifiable) {
                $attachment
                    ->title('Totem Task')
                    ->content($notifiable->description.' just finished running.');
            });
    }
}
```

**Step 4: Update `composer.json` suggest section**

Change:
```json
"suggest": {
    "nexmo/client": "Required for sms notifications."
}
```
To:
```json
"suggest": {
    "laravel/vonage-notification-channel": "Required for SMS notifications via Vonage."
}
```

**Step 5: Update `Task.php` — remove `routeNotificationForNexmo()`**

In `src/Task.php`, rename the nexmo routing method:
```php
// Before
public function routeNotificationForNexmo(): string
{
    return $this->notification_phone_number;
}

// After
public function routeNotificationForVonage(): string
{
    return $this->notification_phone_number;
}
```

**Step 6: Run tests**

```bash
php vendor/bin/phpunit tests/Feature/VonageNotificationTest.php
```

Expected: 3 tests pass.

**Step 7: Run full suite**

```bash
php vendor/bin/phpunit
```

Expected: All pass.

**Step 8: Commit**

```bash
git add src/Notifications/TaskCompleted.php src/Task.php composer.json tests/Feature/VonageNotificationTest.php
git commit -m "feat: replace deprecated Nexmo channel with Vonage"
```

---

## Task 7: Replace `RouteServiceProvider` with inline route registration

**Why:** `Illuminate\Foundation\Support\Providers\RouteServiceProvider` and the `$namespace` / `->namespace()` pattern were removed in Laravel 11. Routes should use tuple syntax `[Controller::class, 'method']`.

**Files:**
- Delete: `src/Providers/TotemRouteServiceProvider.php`
- Modify: `src/Providers/TotemServiceProvider.php`
- Modify: `routes/web.php`
- Delete: `routes/api.php`

**Step 1: Run existing route tests first to establish baseline**

```bash
php vendor/bin/phpunit tests/Feature/ViewDashboardTest.php tests/Feature/ViewTaskTest.php tests/Feature/CreateTaskTest.php
```

Expected: All pass.

**Step 2: Update `routes/web.php`**

Replace the entire file:

```php
<?php

use Illuminate\Support\Facades\Route;
use Studio\Totem\Http\Controllers\ActiveTasksController;
use Studio\Totem\Http\Controllers\DashboardController;
use Studio\Totem\Http\Controllers\ExecuteTasksController;
use Studio\Totem\Http\Controllers\ExportTasksController;
use Studio\Totem\Http\Controllers\ImportTasksController;
use Studio\Totem\Http\Controllers\TasksController;
use Studio\Totem\Http\Controllers\UpcomingTasksController;

Route::get('/', [DashboardController::class, 'index'])->name('totem.dashboard');

Route::prefix('tasks')->group(function () {
    Route::get('/', [TasksController::class, 'index'])->name('totem.tasks.all');

    Route::get('create', [TasksController::class, 'create'])->name('totem.task.create');
    Route::post('create', [TasksController::class, 'store']);

    Route::get('export', [ExportTasksController::class, 'index'])->name('totem.tasks.export');
    Route::post('import', [ImportTasksController::class, 'index'])->name('totem.tasks.import');

    Route::get('upcoming', [UpcomingTasksController::class, 'index'])->name('totem.upcoming');
    Route::get('upcoming/events', [UpcomingTasksController::class, 'events'])->name('totem.upcoming.events');

    Route::get('{totemTask}', [TasksController::class, 'view'])->name('totem.task.view');

    Route::get('{totemTask}/edit', [TasksController::class, 'edit'])->name('totem.task.edit');
    Route::post('{totemTask}/edit', [TasksController::class, 'update']);

    Route::delete('{totemTask}', [TasksController::class, 'destroy'])->name('totem.task.delete');

    Route::post('status', [ActiveTasksController::class, 'store'])->name('totem.task.activate');
    Route::delete('status/{totemTask}', [ActiveTasksController::class, 'destroy'])->name('totem.task.deactivate');

    Route::get('{totemTask}/execute', [ExecuteTasksController::class, 'index'])->name('totem.task.execute');
});
```

**Step 3: Update `src/Providers/TotemServiceProvider.php`**

In `register()`, remove:
```php
$this->app->register(TotemRouteServiceProvider::class);
```

Add this import at top of file:
```php
use Illuminate\Support\Facades\Route;
```

Remove the `use Studio\Totem\Providers\TotemRouteServiceProvider;` import.

Add route registration to `boot()`. Add BEFORE the existing `$this->registerResources()` call:

```php
public function boot(): void
{
    $this->registerResources();
    $this->defineAssetPublishing();
    $this->registerRoutes();
    $this->registerRouteBind();
}

protected function registerRoutes(): void
{
    Route::prefix(config('totem.web.route_prefix', 'totem'))
        ->middleware(config('totem.web.middleware', 'web'))
        ->group(__DIR__.'/../../routes/web.php');
}

protected function registerRouteBind(): void
{
    Route::bind('totemTask', function ($value) {
        return cache()->rememberForever('totem.task.'.$value, function () use ($value) {
            return \Studio\Totem\Task::find($value) ?? abort(404);
        });
    });
}
```

**Step 4: Delete `src/Providers/TotemRouteServiceProvider.php`**

```bash
rm src/Providers/TotemRouteServiceProvider.php
```

**Step 5: Delete empty `routes/api.php`**

```bash
rm routes/api.php
```

Also remove from `config/totem.php` the unused `api` section:
```php
// Remove:
'api' => [
    'middleware' => env('TOTEM_API_MIDDLEWARE', 'api'),
],
```

**Step 6: Run full test suite**

```bash
php vendor/bin/phpunit
```

Expected: All tests pass (routes are registered the same way, just via the new mechanism).

**Step 7: Commit**

```bash
git add src/Providers/TotemServiceProvider.php routes/web.php config/totem.php
git rm src/Providers/TotemRouteServiceProvider.php routes/api.php
git commit -m "refactor: remove RouteServiceProvider, inline routes with tuple syntax"
```

---

## Task 8: Merge `ConsoleServiceProvider` into `TotemServiceProvider`

**Why:** Having a separate `ConsoleServiceProvider` for three lines of code is unnecessary indirection. `TotemServiceProvider` is the canonical entry point.

**Files:**
- Modify: `src/Providers/TotemServiceProvider.php`
- Delete: `src/Providers/ConsoleServiceProvider.php`

**Step 1: Move console boot logic into `TotemServiceProvider::boot()`**

Add to `TotemServiceProvider::boot()`:

```php
use Illuminate\Console\Scheduling\Schedule;
use Studio\Totem\Events\Executed;
use Studio\Totem\Events\Executing;

// Add in boot():
$this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
    if (Totem::isEnabled()) {
        $this->scheduleTotemTasks($schedule);
    }
});
```

Add the private method to the class:

```php
private function scheduleTotemTasks(Schedule $schedule): void
{
    $tasks = app('totem.tasks')->findAllActive();

    $tasks->each(function ($task) use ($schedule) {
        $event = $schedule->command($task->command, $task->compileParameters(true));

        $event->cron($task->getCronExpression())
            ->name($task->description)
            ->timezone($task->timezone)
            ->before(function () use ($task, $event) {
                $event->start = microtime(true);
                Executing::dispatch($task);
            })
            ->thenWithOutput(function ($output) use ($event, $task) {
                Executed::dispatch($task, $event->start ?? microtime(true), $output);
            });

        if ($task->dont_overlap) {
            $event->withoutOverlapping();
        }
        if ($task->run_in_maintenance) {
            $event->evenInMaintenanceMode();
        }
        if ($task->run_on_one_server && in_array(config('cache.default'), ['memcached', 'redis', 'database', 'dynamodb'])) {
            $event->onOneServer();
        }
        if ($task->run_in_background) {
            $event->runInBackground();
        }
    });
}
```

**Step 2: Remove ConsoleServiceProvider registration from `register()`**

Remove:
```php
$this->app->register(ConsoleServiceProvider::class);
```

Remove import: `use Studio\Totem\Providers\ConsoleServiceProvider;`

**Step 3: Delete `src/Providers/ConsoleServiceProvider.php`**

```bash
rm src/Providers/ConsoleServiceProvider.php
```

**Step 4: Run full test suite**

```bash
php vendor/bin/phpunit
```

Expected: All tests pass.

**Step 5: Commit**

```bash
git add src/Providers/TotemServiceProvider.php
git rm src/Providers/ConsoleServiceProvider.php
git commit -m "refactor: merge ConsoleServiceProvider into TotemServiceProvider"
```

---

## Task 9: Remove `request()` from `HasFrequencies` model trait

**Why:** A model trait should not call `request()` — that's an HTTP concern. `processData()` reads from the HTTP request inside a model lifecycle hook, which breaks in queue/console contexts.

**Files:**
- Modify: `src/Traits/HasFrequencies.php`
- Modify: `src/Repositories/EloquentTaskRepository.php`

**Step 1: Remove `processData()` and the `afterSave()` call to it from `HasFrequencies`**

In `src/Traits/HasFrequencies.php`:

Remove the `processData()` method entirely.

Change `afterSave()` to accept the data directly instead of fetching from request:

```php
// Before
public function afterSave()
{
    $input = $this->processData();
    // ...
}

// After
public function afterSave(array $input = []): void
{
    if (isset($input['type'])) {
        if ($input['type'] == 'frequency') {
            foreach ($this->frequencies as $frequency) {
                if (! in_array($frequency->interval, collect($input['frequencies'])->pluck('interval')->toArray())) {
                    $frequency->delete();
                }
            }

            foreach ($input['frequencies'] as $_frequency) {
                $this->frequencies()->updateOrCreate(Arr::only($_frequency, ['task_id', 'label', 'interval']));
            }
        } else {
            $this->frequencies->each(function ($frequency) {
                $frequency->delete();
            });
        }
    }
}
```

Remove the `bootHasFrequencies` saved hook since `afterSave` now needs to be called explicitly:

```php
// Remove the entire bootHasFrequencies static method
// The repository will call afterSave() explicitly after save
```

**Step 2: Update `EloquentTaskRepository` to call `afterSave()` with input data**

In `src/Repositories/EloquentTaskRepository.php`, update `store()` and `update()`:

```php
public function store(array $input): bool|Task
{
    $task = new Task;

    Creating::dispatch($input);

    $task->fill(Arr::only($input, $task->getFillable()))->save();
    $task->afterSave($input);

    Created::dispatch($task);

    return $task;
}

public function update(array $input, $task): Task
{
    $task = $this->find($task);

    Updating::dispatch($input, $task);

    $task->fill(Arr::only($input, $task->getFillable()))->save();
    $task->afterSave($input);

    Updated::dispatch($task);

    return $task;
}
```

For `import()`, pass the task data:

```php
collect(json_decode(Arr::get($input, 'content')))
    ->each(function ($data) {
        $dataArray = (array) $data;
        $this->cache()->forget('totem.task.'.$data->id);

        $task = $this->find($data->id);

        if (is_null($task)) {
            $this->store($dataArray);
            return;
        }

        $this->update($dataArray, $task);
    });
```

Also remove these unused imports from `HasFrequencies.php`:
```php
use Illuminate\Contracts\Filesystem\FileNotFoundException;
// Remove the use function request; line
```

**Step 3: Run full test suite**

```bash
php vendor/bin/phpunit
```

Expected: All tests pass. Pay close attention to `CreateTaskTest` and `EditTaskTest`.

**Step 4: Commit**

```bash
git add src/Traits/HasFrequencies.php src/Repositories/EloquentTaskRepository.php
git commit -m "refactor: remove request() from HasFrequencies model trait"
```

---

## Task 10: Move `src/User.php` to tests, add return types, fix helpers.php

**Why:** (1) `User` model in `src/` can conflict with host app. (2) Several methods missing return types. (3) `helpers.php` hardcodes Font Awesome classes that aren't shipped.

**Files:**
- Move: `src/User.php` → `tests/TestUser.php`
- Modify: `tests/TestCase.php`
- Modify: `src/helpers.php`
- Modify: various `src/` files for return types

**Step 1: Create `tests/TestUser.php`**

```php
<?php

namespace Studio\Totem\Tests;

use Database\Factories\TotemUserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class TestUser extends Authenticatable
{
    use Notifiable, HasFactory;

    protected $table = 'users';

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected static function newFactory(): TotemUserFactory
    {
        return TotemUserFactory::new();
    }
}
```

**Step 2: Update `tests/TestCase.php`**

Change:
```php
use Studio\Totem\User;
// ...
$user = User::factory()->create();
```
To:
```php
use Studio\Totem\Tests\TestUser;
// ...
$user = TestUser::factory()->create();
```

**Step 3: Delete `src/User.php`**

```bash
rm src/User.php
```

**Step 4: Fix `src/helpers.php` — replace Font Awesome with UIKit icons**

Replace the entire file:

```php
<?php

namespace Studio\Totem\Helpers;

use Illuminate\Support\HtmlString;

function columnSort(string $label, string $columnKey, bool $isDefault = false): HtmlString
{
    $icon = '';

    if (request()->has('sort_by')) {
        if (request()->input('sort_by') === $columnKey) {
            $icon = request()->input('sort_direction', 'asc') === 'asc'
                ? ' <span uk-icon="icon: triangle-up; ratio: 0.7"></span>'
                : ' <span uk-icon="icon: triangle-down; ratio: 0.7"></span>';
        }
    } elseif ($isDefault) {
        $icon = request()->input('sort_direction', 'asc') === 'asc'
            ? ' <span uk-icon="icon: triangle-up; ratio: 0.7"></span>'
            : ' <span uk-icon="icon: triangle-down; ratio: 0.7"></span>';
    }

    $order = 'asc';
    if (request()->has('sort_direction')) {
        $order = request()->input('sort_direction') === 'desc' ? 'asc' : 'desc';
    } elseif ($isDefault) {
        $order = 'desc';
    }

    $url = request()->fullUrlWithQuery([
        'sort_by' => $columnKey,
        'sort_direction' => $order,
    ]);

    return new HtmlString('<a href="'.$url.'">'.$label.$icon.'</a>');
}
```

**Step 5: Add missing return types to key PHP files**

In `src/Http/Controllers/TasksController.php`, change `destroy()` signature:
```php
public function destroy(Task $task): RedirectResponse
```

In `src/Http/Controllers/Controller.php`, the constructor already has no return needed.

In `src/Task.php`, change `autoCleanup()`:
```php
public function autoCleanup(): void
```

**Step 6: Run full test suite**

```bash
php vendor/bin/phpunit
```

Expected: All tests pass.

**Step 7: Commit**

```bash
git add tests/TestUser.php tests/TestCase.php src/helpers.php src/Task.php src/Http/Controllers/TasksController.php
git rm src/User.php
git commit -m "refactor: move User model to tests, fix helpers.php icon dependency, add return types"
```

---

## Task 11: Modernize broadcasting — update `TotemEventServiceProvider`

**Why:** The `$listen` array uses string class names. Modern Laravel style uses `::class` constants.

**Files:**
- Modify: `src/Providers/TotemEventServiceProvider.php`

**Step 1: Update `src/Providers/TotemEventServiceProvider.php`**

Replace the entire file:

```php
<?php

namespace Studio\Totem\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider;
use Studio\Totem\Events\Activated;
use Studio\Totem\Events\Created;
use Studio\Totem\Events\Deactivated;
use Studio\Totem\Events\Deleted;
use Studio\Totem\Events\Deleting;
use Studio\Totem\Events\Updated;
use Studio\Totem\Listeners\BuildCache;
use Studio\Totem\Listeners\BustCache;
use Studio\Totem\Listeners\BustCacheImmediately;

class TotemEventServiceProvider extends EventServiceProvider
{
    protected $listen = [
        Created::class => [BustCache::class, BuildCache::class],
        Updated::class => [BustCache::class, BuildCache::class],
        Activated::class => [BustCache::class, BuildCache::class],
        Deactivated::class => [BustCache::class, BuildCache::class],
        Deleting::class => [BustCacheImmediately::class],
    ];
}
```

**Step 2: Run full test suite**

```bash
php vendor/bin/phpunit
```

Expected: All tests pass.

**Step 3: Commit**

```bash
git add src/Providers/TotemEventServiceProvider.php
git commit -m "refactor: use ::class constants in TotemEventServiceProvider"
```

---

## Task 12: Remove untracked `ScheduleRow.vue` and setup Vite + Vue 3 build tooling

**Why:** There's an untracked `ScheduleRow.vue` left from earlier development work. Separately, `laravel-elixir` + `gulp@3` are dead on Node 20+. Replace with Vite 5 + Vue 3.

**Files:**
- Delete: `resources/assets/js/tasks/components/ScheduleRow.vue` (if exists and unused)
- Delete: `webpack.config.js`
- Modify: `package.json`
- Create: `vite.config.js`

**Step 1: Remove untracked ScheduleRow.vue**

```bash
rm resources/assets/js/tasks/components/ScheduleRow.vue
```

**Step 2: Replace `package.json`**

```json
{
  "private": true,
  "scripts": {
    "dev": "vite build --watch",
    "build": "vite build"
  },
  "devDependencies": {
    "@vitejs/plugin-vue": "^5.2",
    "axios": "^1.8",
    "dayjs": "^1.11",
    "uikit": "^3.25",
    "vite": "^6.2",
    "vue": "^3.5"
  }
}
```

**Step 3: Create `vite.config.js`**

```js
import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { resolve } from 'path';

export default defineConfig({
    plugins: [vue()],
    build: {
        lib: {
            entry: resolve(__dirname, 'resources/assets/js/app.js'),
            name: 'TotemApp',
            formats: ['iife'],
            fileName: () => 'app.js',
        },
        outDir: 'public/js',
        emptyOutDir: false,
        cssCodeSplit: false,
        rollupOptions: {
            output: {
                assetFileNames: '../css/components.css',
            },
        },
    },
    resolve: {
        alias: {
            vue: 'vue/dist/vue.esm-bundler.js',
        },
    },
});
```

**Step 4: Delete `webpack.config.js`**

```bash
rm webpack.config.js
```

**Step 5: Install dependencies**

```bash
npm install
```

Expected: node_modules populated with Vue 3, Vite, Day.js, etc. No errors.

**Step 6: Commit (don't build yet — components not migrated)**

```bash
git add package.json vite.config.js
git rm webpack.config.js
git rm -f resources/assets/js/tasks/components/ScheduleRow.vue 2>/dev/null || true
git commit -m "build: replace laravel-elixir/webpack with Vite 5 + Vue 3"
```

---

## Task 13: Migrate `resources/assets/js/app.js` to Vue 3

**Why:** `new Vue()` and `Vue.mixin()` were removed in Vue 3. Replace with `createApp()` and a composable.

**Files:**
- Modify: `resources/assets/js/app.js`
- Create: `resources/assets/js/composables/useFormatDate.js`
- Modify: `resources/assets/js/bootstrap.js`

**Step 1: Read current `resources/assets/js/bootstrap.js`**

Check what it contains (likely axios setup). It should stay mostly the same.

**Step 2: Create `resources/assets/js/composables/useFormatDate.js`**

```js
import dayjs from 'dayjs';

export function useFormatDate() {
    function formatDate(unixTime) {
        return dayjs(unixTime * 1000).add(new Date().getTimezoneOffset() / 60, 'hour');
    }

    function readableTimestamp(timestamp) {
        return formatDate(timestamp).format('HH:mm:ss');
    }

    return { formatDate, readableTimestamp };
}
```

**Step 3: Replace `resources/assets/js/app.js`**

```js
import './bootstrap';
import { createApp } from 'vue';
import UIkit from 'uikit';
import Icons from 'uikit/dist/js/uikit-icons';
import UIKitAlert from './components/UiKitAlert.vue';
import TaskRow from './tasks/components/TaskRow.vue';
import TaskType from './tasks/components/TaskType.vue';
import TaskOutput from './tasks/components/TaskOutput.vue';
import StatusButton from './tasks/components/StatusButton.vue';
import ExecuteButton from './tasks/components/ExecuteButton.vue';
import ImportButton from './tasks/components/ImportButton.vue';
import CommandList from './tasks/components/CommandList.vue';
import ClickToClose from './components/ClickToClose.vue';
import UpcomingCalendar from './tasks/components/UpcomingCalendar.vue';

UIkit.use(Icons);

const app = createApp({});

app.component('uikit-alert', UIKitAlert);
app.component('status-button', StatusButton);
app.component('execute-button', ExecuteButton);
app.component('import-button', ImportButton);
app.component('task-type', TaskType);
app.component('task-output', TaskOutput);
app.component('task-row', TaskRow);
app.component('click-to-close', ClickToClose);
app.component('command-list', CommandList);
app.component('upcoming-calendar', UpcomingCalendar);

app.mount('#root');
```

Note: Remove `Promise.delay` and `Promise.prototype.takeAtLeast` — these will be replaced with a utility function in the next task.

**Step 4: Create `resources/assets/js/utils/takeAtLeast.js`**

```js
export function takeAtLeast(promise, ms) {
    const delay = new Promise(resolve => setTimeout(resolve, ms));
    return Promise.all([promise, delay]).then(([result]) => result);
}
```

**Step 5: Commit**

```bash
git add resources/assets/js/app.js resources/assets/js/composables/useFormatDate.js resources/assets/js/utils/takeAtLeast.js
git commit -m "refactor: migrate app.js to Vue 3 createApp, extract composables"
```

---

## Task 14: Migrate `ClickToClose.vue` and `UIKitModal.vue` to Vue 3

**Why:** `ClickToClose` uses `this.$once('hook:beforeDestroy', ...)` and `this.$slots.default[0]` — both removed in Vue 3. `UIKitModal` uses deprecated `e.keyCode`.

**Files:**
- Modify: `resources/assets/js/components/ClickToClose.vue`
- Modify: `resources/assets/js/components/UIKitModal.vue`

**Step 1: Replace `ClickToClose.vue`**

```vue
<script>
import { h, onMounted, onUnmounted, useSlots } from 'vue';

export default {
    name: 'ClickToClose',
    props: {
        do: {
            type: Function,
            required: true,
        },
    },
    setup(props) {
        const slots = useSlots();

        const listener = (e) => {
            const el = document.querySelector('[data-click-to-close]');
            if (!el || el === e.target || el.contains(e.target)) return;
            props.do();
        };

        onMounted(() => document.addEventListener('click', listener));
        onUnmounted(() => document.removeEventListener('click', listener));

        return () => {
            const defaultSlot = slots.default?.();
            if (!defaultSlot || !defaultSlot.length) return null;
            const child = defaultSlot[0];
            if (child.props) {
                child.props['data-click-to-close'] = true;
            }
            return child;
        };
    },
};
</script>
```

**Step 2: Replace `UIKitModal.vue`**

```vue
<template>
    <transition mode="out-in">
        <div
            v-if="show"
            class="uk-modal uk-flex-top uk-open uk-display-block"
            @click="close"
        >
            <div class="uk-modal-dialog uk-margin-auto-vertical" @click.stop>
                <button class="uk-button uk-button-link uk-modal-close-default" @click="close">
                    <span uk-icon="icon: close"></span>
                </button>
                <slot></slot>
            </div>
        </div>
    </transition>
</template>

<script setup>
import { onMounted, onUnmounted } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close']);

function close() {
    emit('close');
}

function handleKeydown(e) {
    if (props.show && e.key === 'Escape') {
        close();
    }
}

onMounted(() => document.addEventListener('keydown', handleKeydown));
onUnmounted(() => document.removeEventListener('keydown', handleKeydown));
</script>
```

**Step 3: Commit**

```bash
git add resources/assets/js/components/ClickToClose.vue resources/assets/js/components/UIKitModal.vue
git commit -m "refactor: migrate ClickToClose and UIKitModal to Vue 3"
```

---

## Task 15: Migrate `TaskType.vue` — remove `inline-template`

**Why:** `inline-template` was removed in Vue 3. `TaskType.vue` currently has NO `<template>` block — all its HTML lives in `form.blade.php`. The component needs its own template, receiving server-rendered values as props.

**Files:**
- Modify: `resources/assets/js/tasks/components/TaskType.vue`
- Modify: `resources/views/tasks/form.blade.php`
- Modify: `resources/views/dialogs/frequencies/add.blade.php` → move into component

**Step 1: Replace `TaskType.vue` with full SFC**

The component receives server-rendered values as props. Frequencies config is passed as a prop for the "Add Frequency" modal select.

```vue
<template>
    <div class="uk-margin">
        <div class="uk-grid">
            <div class="uk-width-1-1@s uk-width-1-3@m">
                <div class="uk-form-label">Type</div>
                <div class="uk-text-meta">Choose whether to define a cron expression or to add frequencies</div>
            </div>
            <div class="uk-width-1-1@s uk-width-2-3@m uk-form-controls-text">
                <label>
                    <input type="radio" name="type" v-model="type" value="expression"> Expression
                </label><br>
                <label>
                    <input type="radio" name="type" v-model="type" value="frequency"> Frequencies
                </label>
            </div>
        </div>

        <div class="uk-grid" v-if="isCron">
            <div class="uk-width-1-1@s uk-width-1-3@m">
                <label class="uk-form-label">Cron Expression</label>
                <div class="uk-text-meta">Add a cron expression for your task</div>
            </div>
            <div class="uk-width-1-1@s uk-width-2-3@m">
                <input
                    class="uk-input"
                    placeholder="e.g * * * * * to run this task all the time"
                    name="expression"
                    id="expression"
                    :value="expressionValue"
                    type="text"
                >
                <p v-if="expressionError" class="uk-text-danger">{{ expressionError }}</p>
            </div>
        </div>

        <div class="uk-grid" v-if="managesFrequencies">
            <div class="uk-width-1-1@s uk-width-1-3@m">
                <label class="uk-form-label">Frequencies</label>
                <div class="uk-text-meta">Add frequencies to your task. These will be converted into a cron expression while scheduling.</div>
            </div>
            <div class="uk-width-1-1@s uk-width-2-3@m">
                <a class="uk-button uk-button-small uk-button-link" @click.prevent="showModal = true">Add Frequency</a>

                <!-- Add Frequency Modal -->
                <uikit-modal :show="showModal" @close="closeModal">
                    <div class="uk-modal-header">
                        <h3>Add Frequency</h3>
                    </div>
                    <div class="uk-modal-body">
                        <fieldset class="uk-fieldset">
                            <div class="uk-margin">
                                <select id="frequency" class="uk-select" v-model="selected">
                                    <option :value="placeholder" disabled>Select a type of frequency</option>
                                    <option v-for="freq in frequenciesConfig" :key="freq.interval" :value="freq">
                                        {{ freq.label }}
                                    </option>
                                </select>
                            </div>
                            <div v-if="selected.parameters">
                                <div class="uk-margin" v-for="parameter in selected.parameters" :key="parameter.name">
                                    <input
                                        type="text"
                                        v-model="parameter.value"
                                        :name="parameter.name"
                                        :placeholder="parameter.label"
                                        class="uk-input"
                                    >
                                </div>
                            </div>
                        </fieldset>
                    </div>
                    <div class="uk-modal-footer">
                        <div class="uk-flex uk-flex-right">
                            <button class="uk-button uk-button-small uk-button-primary" @click.prevent="addFrequency">Add</button>
                        </div>
                    </div>
                </uikit-modal>

                <table class="uk-table uk-table-divider uk-margin-remove">
                    <thead>
                        <tr>
                            <th class="uk-padding-remove-left">Frequency</th>
                            <th class="uk-padding-remove-left">Parameters</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(frequency, index) in frequencies" :key="index">
                            <td class="uk-padding-remove-left">
                                {{ frequency.label }}
                                <input type="hidden" :name="'frequencies[' + index + '][interval]'" v-model="frequency.interval">
                                <input type="hidden" :name="'frequencies[' + index + '][label]'" v-model="frequency.label">
                            </td>
                            <td class="uk-padding-remove-left">
                                <span v-if="frequency.parameters && frequency.parameters.length > 0">
                                    <span v-for="(parameter, key) in frequency.parameters" :key="key">
                                        {{ parameter.value }}
                                        <span v-if="frequency.parameters.length > 1 && key < frequency.parameters.length - 1">,</span>
                                        <input type="hidden" :name="'frequencies[' + index + '][parameters][' + key + '][name]'" v-model="parameter.name">
                                        <input type="hidden" :name="'frequencies[' + index + '][parameters][' + key + '][value]'" v-model="parameter.value">
                                    </span>
                                </span>
                                <span v-else>No Parameters</span>
                            </td>
                            <td>
                                <a class="uk-button uk-button-link" @click="remove(index)">
                                    <span uk-icon="icon: close"></span>
                                </a>
                            </td>
                        </tr>
                        <tr v-if="frequencies.length === 0">
                            <td colspan="3" class="uk-padding-remove-left">No Frequencies Found</td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="frequenciesError" class="uk-text-danger">{{ frequenciesError }}</p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import UIKitModal from '../../components/UIKitModal.vue';

const props = defineProps({
    current: {
        type: String,
        default: 'frequency',
    },
    existing: {
        type: Array,
        default: () => [],
    },
    expressionValue: {
        type: String,
        default: '',
    },
    expressionError: {
        type: String,
        default: '',
    },
    frequenciesError: {
        type: String,
        default: '',
    },
    frequenciesConfig: {
        type: Array,
        default: () => [],
    },
});

const placeholder = { label: 'Please select a frequency', interval: false, parameters: false };

const type = ref(props.current);
const frequencies = ref(props.existing ? [...props.existing] : []);
const showModal = ref(false);
const selected = ref({ ...placeholder });

const isCron = computed(() => type.value === 'expression');
const managesFrequencies = computed(() => type.value === 'frequency');

function addFrequency() {
    if (selected.value.interval) {
        frequencies.value.push({ ...selected.value, parameters: selected.value.parameters ? selected.value.parameters.map(p => ({ ...p })) : [] });
        closeModal();
    }
}

function closeModal() {
    selected.value = { ...placeholder };
    showModal.value = false;
}

function remove(index) {
    frequencies.value.splice(index, 1);
}
</script>
```

**Step 2: Update `resources/views/tasks/form.blade.php`**

Replace the `<task-type inline-template ...>...</task-type>` block (lines 63–147) with:

```blade
<task-type
    current="{{ old('type', $task->expression ? 'expression' : 'frequency') }}"
    :existing="{{ old('frequencies') ? json_encode(old('frequencies')) : $task->frequencies }}"
    expression-value="{{ old('expression', $task->expression) }}"
    expression-error="{{ $errors->first('expression') }}"
    frequencies-error="{{ $errors->first('frequencies') }}"
    :frequencies-config="{{ json_encode($frequencies) }}"
></task-type>
```

**Step 3: Delete `resources/views/dialogs/frequencies/add.blade.php`** (now replaced by the inline modal in the Vue component)

```bash
rm resources/views/dialogs/frequencies/add.blade.php
```

**Step 4: Commit**

```bash
git add resources/assets/js/tasks/components/TaskType.vue resources/views/tasks/form.blade.php
git rm resources/views/dialogs/frequencies/add.blade.php
git commit -m "refactor: remove inline-template from TaskType.vue, convert to proper SFC"
```

---

## Task 16: Migrate remaining Vue components to Vue 3 Composition API

**Why:** All remaining components use Vue 2 Options API patterns. Convert to `<script setup>` Composition API. Also fix `is="task-row"` in the index Blade view.

**Files:**
- Modify: `resources/assets/js/components/UIKitAlert.vue`
- Modify: `resources/assets/js/tasks/components/StatusButton.vue`
- Modify: `resources/assets/js/tasks/components/ExecuteButton.vue`
- Modify: `resources/assets/js/tasks/components/TaskRow.vue`
- Modify: `resources/assets/js/tasks/components/TaskOutput.vue`
- Modify: `resources/assets/js/tasks/components/ImportButton.vue`
- Modify: `resources/assets/js/tasks/components/CommandList.vue`
- Modify: `resources/views/tasks/index.blade.php`

**Step 1: Read `UIKitAlert.vue` first**, then convert.

```bash
cat resources/assets/js/components/UIKitAlert.vue
```

Convert to `<script setup>` — should be straightforward.

**Step 2: Migrate `StatusButton.vue`**

Replace `Promise.prototype.takeAtLeast` with the `takeAtLeast` utility:

```vue
<script setup>
import { ref, computed } from 'vue';
import { takeAtLeast } from '../../utils/takeAtLeast.js';

const props = defineProps({
    dataTask: { type: Object, default: null },
    dataExists: { type: Boolean, default: false },
    activateUrl: { type: String, required: true },
    deactivateUrl: { type: String, required: true },
});

const hovering = ref(false);
const working = ref(false);
const task = ref(props.dataTask);
const exists = ref(props.dataExists);

const inActiveStatusText = computed(() => hovering.value ? 'Enable' : 'Disabled');
const activeStatusText = computed(() => hovering.value ? 'Disable' : 'Enabled');
const existsAndIsInActive = computed(() => !task.value.activated && exists.value);

async function activate() {
    working.value = true;
    try {
        const response = await takeAtLeast(axios.post(props.activateUrl, { task_id: props.dataTask.id }), 500);
        task.value = response.data;
    } finally {
        working.value = false;
        hovering.value = false;
    }
}

async function deactivate() {
    working.value = true;
    try {
        const response = await takeAtLeast(axios.delete(props.deactivateUrl), 500);
        task.value = response.data;
    } finally {
        working.value = false;
        hovering.value = false;
    }
}
</script>
```

Keep the existing `<template>` block — it is already valid Vue 3 HTML.

**Step 3: Migrate `ExecuteButton.vue`**

```vue
<script setup>
import { ref, computed } from 'vue';
import { takeAtLeast } from '../../utils/takeAtLeast.js';

const props = defineProps({
    dataTask: {},
    url: { type: String, required: true },
    iconName: { type: String, default: null },
    buttonClass: { type: String, default: 'uk-button-small' },
});

const emit = defineEmits(['taskExecuted']);

const running = ref(false);
const task = ref(props.dataTask);

const buttonClasses = computed(() => running.value ? 'uk-spinner uk-icon' : props.buttonClass);

async function execute() {
    running.value = true;
    try {
        const response = await takeAtLeast(axios.get(props.url), 500);
        task.value = response.data;
        emit('taskExecuted', task.value);
    } finally {
        running.value = false;
    }
}
</script>
```

Keep the existing `<template>`.

**Step 4: Migrate `TaskRow.vue`** (also replaces `moment` with `dayjs`)

```vue
<script setup>
import { ref, computed } from 'vue';
import dayjs from 'dayjs';
import ExecuteButton from './ExecuteButton.vue';

const props = defineProps({
    dataTask: {},
});

const task = ref(props.dataTask);

const description = computed(() => task.value.description.substring(0, 29));
const averageDurationInSeconds = computed(() =>
    task.value.average_runtime > 0 ? (task.value.average_runtime / 1000).toFixed(2) : 0
);
const lastRunDate = computed(() =>
    dayjs(task.value.last_result.ran_at).format('YYYY-MM-DD HH:mm:ss')
);
const showHref = computed(() => task.value.showhref ?? '');
const executeHref = computed(() => task.value.executehref ?? '');

function refreshTask(updatedTask) {
    task.value = updatedTask;
}
</script>
```

Keep `<template>` but update `$attrs.showhref` → use the computed `showHref` and `executeHref` from props.

**Step 5: Migrate `TaskOutput.vue`**

```vue
<script setup>
import { ref } from 'vue';
import UIKitModal from '../../components/UIKitModal.vue';

const props = defineProps({
    output: { type: String, default: '' },
});

const showModal = ref(false);
</script>
```

Keep `<template>`.

**Step 6: Migrate `ImportButton.vue`**

```vue
<script setup>
import { ref, onMounted } from 'vue';
import UIkit from 'uikit';

const props = defineProps({
    url: { type: String, required: true },
});

const importing = ref(false);

onMounted(() => {
    UIkit.upload('.js-upload', {
        url: props.url,
        method: 'POST',
        name: 'tasks',
        beforeSend(environment) {
            environment.headers['X-CSRF-TOKEN'] = window.axios.defaults.headers.common['X-CSRF-TOKEN'];
        },
        beforeAll() {
            importing.value = true;
        },
        completeAll() {
            importing.value = false;
            window.location.reload(true);
        },
    });
});
</script>
```

Keep `<template>`.

**Step 7: Migrate `CommandList.vue`**

```vue
<script setup>
import { ref, computed, nextTick } from 'vue';
import ClickToClose from '../../components/ClickToClose.vue';

const props = defineProps({
    command: { type: String, default: '' },
    commands: { type: [Array, Object], default: () => [] },
});

const input = ref(null);
const search = ref(null);
const options = ref(null);

const selected = ref(decodeURI(props.command));
const allOptions = ref(Object.values(props.commands));
const isOpen = ref(false);
const searchText = ref('');
const highlightedIndex = ref(0);

const filteredOptions = computed(() =>
    allOptions.value.filter(option => option.name.toLowerCase().includes(searchText.value.toLowerCase()))
);

function open() {
    if (isOpen.value) return;
    isOpen.value = true;
    highlightedIndex.value = allOptions.value.findIndex(o => o.name === selected.value);
    nextTick(() => {
        search.value?.focus();
        scrollToHighlighted();
    });
}

function close() {
    if (!isOpen.value) return;
    isOpen.value = false;
    input.value?.focus();
}

function select(option) {
    selected.value = option.name;
    searchText.value = '';
    highlightedIndex.value = 0;
    close();
}

function selectHighlighted() {
    select(filteredOptions.value[highlightedIndex.value]);
}

function scrollToHighlighted() {
    options.value?.children[highlightedIndex.value]?.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'start' });
}

function highlight(index) {
    highlightedIndex.value = index;
    if (highlightedIndex.value < 0) highlightedIndex.value = filteredOptions.value.length - 1;
    if (highlightedIndex.value > filteredOptions.value.length - 1) highlightedIndex.value = 0;
    scrollToHighlighted();
}

function highlightNext() { highlight(highlightedIndex.value + 1); }
function highlightPrev() { highlight(highlightedIndex.value - 1); }
</script>
```

Keep `<template>` but update `v-model="search"` → `v-model="searchText"` and `$refs` to the template refs.

**Step 8: Fix `index.blade.php` — replace `is="task-row"` with `<task-row>`**

```blade
@forelse($tasks as $task)
    <task-row
        :data-task="{{ $task }}"
        show-href="{{ route('totem.task.view', ['totemTask' => $task]) }}"
        execute-href="{{ route('totem.task.execute', ['totemTask' => $task]) }}"
    ></task-row>
@empty
```

Note: `TaskRow` renders a `<tr>` element. Update the computed props in `TaskRow.vue` to use `attrs.showHref` / `attrs.executeHref` passed as props rather than `$attrs`.

**Step 9: Commit**

```bash
git add resources/assets/js/ resources/views/tasks/index.blade.php
git commit -m "refactor: migrate all Vue components to Vue 3 Composition API"
```

---

## Task 17: Replace `moment.js` with `Day.js` in `UpcomingCalendar.vue`

**Why:** `moment.js` is ~290KB; `dayjs` is ~7KB with an identical API for the operations used. `TaskRow.vue` was already handled in Task 16.

**Files:**
- Modify: `resources/assets/js/tasks/components/UpcomingCalendar.vue`

**Step 1: Replace `import moment from 'moment'` with `import dayjs from 'dayjs'`**

In `UpcomingCalendar.vue`, change the import:
```js
// Before
import moment from 'moment';

// After
import dayjs from 'dayjs';
```

Replace all `moment(...)` calls with `dayjs(...)`. The API is identical for the methods used (`format`, `add`, `subtract`, `startOf`, `isValid`, `toDate`, `isBefore`, `copy`→`clone`).

Key differences:
- `moment().startOf('day').toDate()` → `dayjs().startOf('day').toDate()`
- `moment(date).format('YYYY-MM-DD')` → `dayjs(date).format('YYYY-MM-DD')`
- `moment(startParam).isValid()` → `dayjs(startParam).isValid()`
- `moment(this.currentStart).subtract(n, 'days')` → `dayjs(this.currentStart).subtract(n, 'days')`

**Step 2: Commit**

```bash
git add resources/assets/js/tasks/components/UpcomingCalendar.vue
git commit -m "refactor: replace moment.js with dayjs in UpcomingCalendar"
```

---

## Task 18: Build the frontend bundle and verify

**Why:** Produce the updated `public/js/app.js` bundle with Vue 3 + Vite.

**Files:**
- Rebuild: `public/js/app.js`

**Step 1: Run the build**

```bash
npm run build
```

Expected: Build completes without errors. `public/js/app.js` updated.

If there are CSS files generated (e.g. `public/js/style.css`), update the Blade layout to also reference it:

In `resources/views/layout.blade.php`, after the existing CSS link, add:
```blade
@if(file_exists(public_path('vendor/totem/js/style.css')))
    <link rel="stylesheet" type="text/css" href="{{ asset('/vendor/totem/js/style.css') }}">
@endif
```

**Step 2: Run the full PHP test suite to make sure nothing broke**

```bash
php vendor/bin/phpunit
```

Expected: All tests pass.

**Step 3: Commit**

```bash
git add public/js/app.js public/js/style.css resources/views/layout.blade.php
git commit -m "build: rebuild frontend bundle with Vue 3 + Vite + dayjs"
```

---

## Task 19: Final cleanup — remove dead code and verify CI matrix

**Why:** Ensure no dead imports, no leftover Vue 2 patterns, and that all defined success criteria pass.

**Files:**
- Review: all modified files
- Modify: `composer.json` (cleanup autoload for removed `src/User.php`)

**Step 1: Remove `Studio\Totem\User` from autoload if needed**

In `composer.json`, ensure the `autoload.psr-4` section does not reference `src/User.php` explicitly (it shouldn't — PSR-4 auto-discovers by namespace).

**Step 2: Run final full test suite**

```bash
php vendor/bin/phpunit --testdox
```

Expected: All tests pass with descriptive output.

**Step 3: Verify no global constant references remain**

```bash
grep -r "TOTEM_TABLE_PREFIX\|TOTEM_DATABASE_CONNECTION\|TOTEM_PATH" src/ tests/
```

Expected: No output (zero matches).

**Step 4: Verify no deprecated patterns remain**

```bash
grep -r "Validator::extend\|CronExpression::factory\|NexmoMessage\|nexmo\|inline-template\|Vue\.mixin\|new Vue(" src/ resources/
```

Expected: No output.

**Step 5: Verify no moment.js references remain**

```bash
grep -r "import moment\|from 'moment'" resources/
```

Expected: No output.

**Step 6: Commit**

```bash
git add composer.json
git commit -m "chore: final cleanup and verification of modernization"
```

---

## Final: Run the complete test suite

```bash
php vendor/bin/phpunit
```

Expected output: All tests green. The test count should be higher than the original 46 due to the new rule and notification tests added.

---

## Success Checklist

- [ ] All existing tests pass
- [ ] New rule and notification tests pass
- [ ] `npm run build` produces `public/js/app.js` without errors
- [ ] No PHP global constants (`TOTEM_*`) in `src/`
- [ ] No deprecated `Validator::extend()` calls
- [ ] No `CronExpression::factory()` calls
- [ ] No `NexmoMessage` / `nexmo` channel references
- [ ] No `RouteServiceProvider` extension
- [ ] No `inline-template` usage
- [ ] No `Vue.mixin()` global mixin
- [ ] No `moment` import
- [ ] `src/User.php` deleted, `tests/TestUser.php` in its place
- [ ] `routes/api.php` deleted
- [ ] `src/Providers/TotemRouteServiceProvider.php` deleted
- [ ] `src/Providers/ConsoleServiceProvider.php` deleted
- [ ] `resources/views/dialogs/frequencies/add.blade.php` deleted
- [ ] `webpack.config.js` deleted
