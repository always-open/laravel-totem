# Totem v13.0 Type Sweep (PR 4) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add missing PHP parameter and return type hints across `src/`, promote PHPDoc `@param` stubs that lack types to real type hints, and add array-shape annotations on the three `EloquentTaskRepository` entry-point methods (`store`, `update`, `import`). Pure documentation and type-declaration work — zero behavioral changes.

**Architecture:** File-by-file sweep starting with the most obvious gaps (like `src/Events/Executed.php`'s `$started`/`$output` untyped params) and ending with the array-shape annotations on the repository. After each file edit, the Pest suite runs to prove no behavior changed. Pint runs at the end of the PR for style.

**Tech Stack:** PHP 8.3+, Laravel Pint. Branch: `feat/type-sweep` off `13.x`. Prerequisite: PR 2 merged. Can land in parallel with PR 3, but easier if PR 3 is merged first (so `vendor/bin/pest` is the verification command).

**Spec:** `docs/plans/2026-04-21-totem-v13-release-design.md` §4 (Section 4: PR 4). ACs AC4.1–AC4.6.

---

## File Structure

No new files. Files touched are in `src/` only:

| Category | Files (all in `src/`) |
|----------|-----------------------|
| Events (likely untyped constructor params from project legacy) | `Events/Executed.php`, `Events/Executing.php`, `Events/Creating.php`, `Events/Updating.php`, `Events/Deleting.php`, `Events/TaskEvent.php`, `Events/Event.php`, `Events/BroadcastingEvent.php`, `Events/Activated.php`, `Events/Created.php`, `Events/Deactivated.php`, `Events/Deleted.php`, `Events/Updated.php` |
| Repository entry points (array shapes) | `Repositories/EloquentTaskRepository.php` |
| HTTP | `Http/Controllers/*.php`, `Http/Middleware/Authenticate.php`, `Http/Requests/TaskRequest.php`, `Http/Requests/ImportRequest.php` |
| Listeners | `Listeners/BuildCache.php`, `Listeners/BustCache.php`, `Listeners/BustCacheImmediately.php`, `Listeners/Listener.php` |
| Notifications | `Notifications/TaskCompleted.php` |
| Console | `Console/Commands/ListSchedule.php`, `Console/Commands/PublishAssets.php` |
| Models / Core | `Task.php`, `Result.php`, `Frequency.php`, `Parameter.php`, `Totem.php`, `TotemModel.php`, `helpers.php` |
| Providers | `Providers/TotemServiceProvider.php`, `Providers/TotemEventServiceProvider.php` |
| Rules | `Rules/CronExpression.php`, `Rules/JsonFile.php` |
| Traits | `Traits/FrontendSortable.php`, `Traits/HasFrequencies.php`, `Traits/HasParameters.php` |
| Contracts | `Contracts/TaskInterface.php` |
| Database | `Database/TotemMigration.php` |

The sweep touches files selectively — only those with actual gaps. Many files may already have complete type coverage and need no change.

---

## Task 1: Branch setup

- [ ] **Step 1: Verify 13.x is up to date**

```bash
git checkout 13.x
git pull --ff-only origin 13.x
git log -1 --format='%h %s'
```

Expected: HEAD is the merge commit for PR 3 (Pest migration) if that's landed, or PR 2 (baseline) otherwise. Both are valid starting points.

- [ ] **Step 2: Create the PR branch**

```bash
git checkout -b feat/type-sweep
```

- [ ] **Step 3: Determine the test runner**

```bash
ls vendor/bin/pest vendor/bin/phpunit 2>/dev/null
```

Set an environment alias for the rest of this plan:

```bash
# If PR 3 has merged:
TEST_RUN="APP_ENV=testing vendor/bin/pest"
# Otherwise:
TEST_RUN="APP_ENV=testing vendor/bin/phpunit"
echo "Test runner: $TEST_RUN"
```

All subsequent "run tests" steps use `$TEST_RUN`.

- [ ] **Step 4: Baseline run**

```bash
$TEST_RUN
```

Expected: all green. This is the "no regressions" baseline. If it fails, STOP — PR 2 or PR 3 didn't land cleanly.

---

## Task 2: Scan src/ for missing type annotations

Goal: produce a concrete list of methods needing attention before editing.

- [ ] **Step 1: List methods with untyped parameters**

```bash
grep -rn 'function [a-zA-Z_]*([^)]*\$[a-zA-Z_]' src/ \
  | grep -vE 'function [a-zA-Z_]*\([^)]*(bool|int|float|string|array|mixed|null|self|static|void|object|iterable|callable|[A-Z][a-zA-Z]*)\s*\$' \
  | grep -v 'vendor/' \
  > /tmp/untyped-params.txt
wc -l /tmp/untyped-params.txt
head -30 /tmp/untyped-params.txt
```

The output is an imperfect filter — some lines may be false positives (method defaults containing `$`, complex signatures split across lines). Use it as a hit list, not a ground truth.

- [ ] **Step 2: List methods with missing return types**

```bash
grep -rn 'function [a-zA-Z_][a-zA-Z_0-9]*([^)]*)\s*$\|function [a-zA-Z_][a-zA-Z_0-9]*([^)]*) *{' src/ \
  | grep -v ': [a-zA-Z]' \
  > /tmp/missing-return.txt
wc -l /tmp/missing-return.txt
head -30 /tmp/missing-return.txt
```

Same caveat — imperfect grep heuristic.

- [ ] **Step 3: List PHPDoc `@param` entries without types**

```bash
grep -rn '@param \$' src/ > /tmp/untyped-phpdoc.txt
cat /tmp/untyped-phpdoc.txt
```

These are explicit design-spec targets — each one needs resolution.

- [ ] **Step 4: Sanity check against spec**

Confirm the known target (per spec §4.2 Example): `src/Events/Executed.php::__construct(Task $task, $started, $output)`. Verify it's in the hit list:

```bash
grep 'Executed.php' /tmp/untyped-params.txt
```

If the `Executed.php` constructor doesn't surface, the grep heuristic is failing; fall back to reading each event file manually in Task 3.

---

## Task 3: Fix `src/Events/Executed.php` (concrete example)

This task sets the pattern. All other event files use the same treatment.

**Files:**
- Modify: `src/Events/Executed.php`

- [ ] **Step 1: Read the current file**

```bash
cat src/Events/Executed.php
```

Current content:

```php
<?php

namespace Studio\Totem\Events;

use Studio\Totem\Notifications\TaskCompleted;
use Studio\Totem\Task;

class Executed extends BroadcastingEvent
{
    /**
     * Executed constructor.
     *
     * @param  string|float|int  $started
     * @param  $output
     */
    public function __construct(Task $task, $started, $output)
    {
        parent::__construct($task);

        $time_elapsed_secs = microtime(true) - $started;

        $task->results()->create([
            'duration' => $time_elapsed_secs * 1000,
            'result' => $output,
        ]);

        $task->notify(new TaskCompleted($output));
        $task->autoCleanup();
    }
}
```

Two gaps:
- `$started` is untyped in the signature; PHPDoc says `string|float|int`.
- `$output` is untyped everywhere.

- [ ] **Step 2: Determine actual runtime types**

Check the callers:

```bash
grep -rn 'Executed::dispatch\|new Executed' src/
```

Expected callers (from earlier exploration):
- `src/Repositories/EloquentTaskRepository.php:189` — `Executed::dispatch($task, $start, $output);` where `$start = microtime(true)` (float) and `$output = Artisan::output()` (string) or `$e->getMessage()` (string).
- `src/Providers/TotemServiceProvider.php:129` — `Executed::dispatch($task, $event->start ?? microtime(true), $output);` where `$output` comes from `thenWithOutput(function ($output) ...)` which can be any string (including empty).

So:
- `$started`: always `float` (microtime result). The PHPDoc `string|float|int` is overly broad.
- `$output`: always `string` at call site. Could technically be null if a caller constructs with null, but current callers never do. Use `string` to be strict.

- [ ] **Step 3: Apply the type fix**

Replace the constructor and PHPDoc with:

```php
<?php

namespace Studio\Totem\Events;

use Studio\Totem\Notifications\TaskCompleted;
use Studio\Totem\Task;

class Executed extends BroadcastingEvent
{
    public function __construct(Task $task, float $started, string $output)
    {
        parent::__construct($task);

        $time_elapsed_secs = microtime(true) - $started;

        $task->results()->create([
            'duration' => $time_elapsed_secs * 1000,
            'result' => $output,
        ]);

        $task->notify(new TaskCompleted($output));
        $task->autoCleanup();
    }
}
```

Changes:
- `$started` → `float $started`.
- `$output` → `string $output`.
- PHPDoc block removed entirely — the signature now carries the type information the PHPDoc was attempting to convey, and the "Executed constructor." description line added no value (method name already says "__construct" and class name says "Executed").

- [ ] **Step 4: Run the test suite**

```bash
$TEST_RUN
```

Expected: green. Specifically `tests/Feature/TaskExecutionTest.php` exercises this code path; if it fails with a type error, a caller was passing a non-float/non-string. Either:

- Fix the caller (if that's a bug), OR
- Widen the type (e.g., `int|float $started`) and document why.

If the widening is needed, record it in the PR body — the spec explicitly allows this: "where the type is inferable from usage".

- [ ] **Step 5: Commit**

```bash
git add src/Events/Executed.php
git commit -m "types: tighten Executed constructor parameter types"
```

---

## Task 4: Apply the same pattern to every Event class

Goal: iterate through `src/Events/*.php` and apply type fixes where needed.

For each file below, perform the read → identify gaps → patch → run tests → commit cycle (like Task 3):

- [ ] `src/Events/Executing.php`
- [ ] `src/Events/Creating.php`
- [ ] `src/Events/Updating.php`
- [ ] `src/Events/Deleting.php`
- [ ] `src/Events/TaskEvent.php`
- [ ] `src/Events/Event.php`
- [ ] `src/Events/BroadcastingEvent.php`
- [ ] `src/Events/Activated.php`
- [ ] `src/Events/Created.php`
- [ ] `src/Events/Deactivated.php`
- [ ] `src/Events/Deleted.php`
- [ ] `src/Events/Updated.php`

**Conversion rules (apply uniformly):**

- Every parameter gets a type hint if one is inferable from usage. Callers can be found with `grep -rn 'ClassName::dispatch\|new ClassName' src/`.
- Every public/protected method gets a return type if inferable. For event constructors and `__construct` methods, no return type declaration is legal (void is implied); prefer leaving these unannotated to match PHP's standard practice.
- PHPDoc that merely restates the signature (`@param Task $task The task`) is removed.
- PHPDoc that adds semantic value (why, when, invariants) is retained and tightened.

**Commit granularity:** one commit per event file touched. This keeps git history readable during review and makes reverts surgical.

Skip any event file that already has full type coverage and no stale PHPDoc — confirm by eye, then move on without a commit.

**After each commit:** `$TEST_RUN` green. Pest output must show 0 failures.

---

## Task 5: Sweep non-event src/ files

Goal: apply the same type/PHPDoc treatment to every non-event file in `src/`.

For each category below, enumerate the files (already listed in File Structure), read each, patch where gaps exist, run tests, commit per-file.

Keep commits as one-per-file so reviewer diff is digestible. Commit message prefix: `types: ` (not `feat:` or `fix:` — this is not a behavior change).

**Order of work** (easier files first, harder last):

### 5a. Rules

- [ ] `src/Rules/CronExpression.php` — read, patch, test, commit.
- [ ] `src/Rules/JsonFile.php` — read, patch, test, commit.

Rules typically have well-defined signatures (they implement `Illuminate\Contracts\Validation\Rule`); gaps are limited to PHPDoc cleanup.

### 5b. Traits

- [ ] `src/Traits/FrontendSortable.php`
- [ ] `src/Traits/HasFrequencies.php`
- [ ] `src/Traits/HasParameters.php`

### 5c. Console commands

- [ ] `src/Console/Commands/ListSchedule.php`
- [ ] `src/Console/Commands/PublishAssets.php`

### 5d. Listeners

- [ ] `src/Listeners/Listener.php`
- [ ] `src/Listeners/BuildCache.php`
- [ ] `src/Listeners/BustCache.php`
- [ ] `src/Listeners/BustCacheImmediately.php`

### 5e. Http layer

- [ ] `src/Http/Middleware/Authenticate.php`
- [ ] `src/Http/Controllers/Controller.php`
- [ ] `src/Http/Controllers/ActiveTasksController.php`
- [ ] `src/Http/Controllers/DashboardController.php`
- [ ] `src/Http/Controllers/ExecuteTasksController.php`
- [ ] `src/Http/Controllers/ExportTasksController.php`
- [ ] `src/Http/Controllers/ImportTasksController.php`
- [ ] `src/Http/Controllers/TasksController.php`
- [ ] `src/Http/Controllers/UpcomingTasksController.php`
- [ ] `src/Http/Requests/TaskRequest.php`
- [ ] `src/Http/Requests/ImportRequest.php`

### 5f. Notifications

- [ ] `src/Notifications/TaskCompleted.php` — likely already well-typed (constructor uses `private readonly string $output`); verify and skip if clean.

### 5g. Core models

- [ ] `src/TotemModel.php`
- [ ] `src/Task.php`
- [ ] `src/Result.php`
- [ ] `src/Frequency.php`
- [ ] `src/Parameter.php`
- [ ] `src/Totem.php`
- [ ] `src/helpers.php`

### 5h. Contracts, Database, Providers

- [ ] `src/Contracts/TaskInterface.php`
- [ ] `src/Database/TotemMigration.php`
- [ ] `src/Providers/TotemServiceProvider.php`
- [ ] `src/Providers/TotemEventServiceProvider.php`

**Scope reminder (AC4.6):** Each commit in this task contains ONLY type/PHPDoc changes. If a file review surfaces a genuine bug or questionable behavior, extract that into a **separate** PR. Do not mix.

---

## Task 6: Array-shape annotations on `EloquentTaskRepository`

Goal: document the actual array shapes for `store()`, `update()`, and `import()` per spec §4.2. The shapes are enumerated from `Task::$fillable` (16 fields) and the import payload.

**Files:**
- Modify: `src/Repositories/EloquentTaskRepository.php`

- [ ] **Step 1: Review the fillable list**

```bash
grep -A17 'protected \$fillable' src/Task.php
```

Expected (current Totem `Task::$fillable`):

```php
protected $fillable = [
    'id',
    'description',
    'command',
    'parameters',
    'expression',
    'timezone',
    'is_active',
    'dont_overlap',
    'run_in_maintenance',
    'notification_email_address',
    'notification_phone_number',
    'notification_slack_webhook',
    'auto_cleanup_type',
    'auto_cleanup_num',
    'run_on_one_server',
    'run_in_background',
];
```

- [ ] **Step 2: Annotate `store()`**

Current (lines 88–104):

```php
    /**
     * Create a new task.
     */
    public function store(array $input): bool|Task
    {
        $task = new Task;

        Creating::dispatch($input);

        $task->fill(Arr::only($input, $task->getFillable()))->save();
        $task->afterSave($input);

        Created::dispatch($task);

        return $task;
    }
```

Replace with:

```php
    /**
     * Create a new task.
     *
     * Array shape is the union of Task's fillable columns plus extra
     * `frequencies` and `parameters` keys consumed by Task::afterSave().
     * `Arr::only($input, $task->getFillable())` filters unknown keys
     * before mass-assignment.
     *
     * @param array{
     *     description?: string,
     *     command?: string,
     *     parameters?: array<int|string, mixed>,
     *     expression?: string,
     *     timezone?: string,
     *     is_active?: bool,
     *     dont_overlap?: bool,
     *     run_in_maintenance?: bool,
     *     notification_email_address?: string|null,
     *     notification_phone_number?: string|null,
     *     notification_slack_webhook?: string|null,
     *     auto_cleanup_type?: string|null,
     *     auto_cleanup_num?: int|null,
     *     run_on_one_server?: bool,
     *     run_in_background?: bool,
     *     frequencies?: array<int, array<string, mixed>>
     * } $input
     */
    public function store(array $input): bool|Task
    {
        $task = new Task;

        Creating::dispatch($input);

        $task->fill(Arr::only($input, $task->getFillable()))->save();
        $task->afterSave($input);

        Created::dispatch($task);

        return $task;
    }
```

Note: `id` is intentionally omitted from the `store()` shape (id is auto-assigned). `frequencies` is included because `Task::afterSave()` (invoked right after fill+save) consumes this key to create related frequency rows.

- [ ] **Step 3: Annotate `update()`**

Current (lines 106–121):

```php
    /**
     * Update the given task.
     */
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

Replace with:

```php
    /**
     * Update the given task.
     *
     * Shares the shape of store()'s $input, with `id` present (update
     * lookup may use it) and most other keys optional (partial update).
     * The `$task` parameter accepts a Task instance or an id; it is
     * resolved via find() before mutation.
     *
     * @param array{
     *     id?: int,
     *     description?: string,
     *     command?: string,
     *     parameters?: array<int|string, mixed>,
     *     expression?: string,
     *     timezone?: string,
     *     is_active?: bool,
     *     dont_overlap?: bool,
     *     run_in_maintenance?: bool,
     *     notification_email_address?: string|null,
     *     notification_phone_number?: string|null,
     *     notification_slack_webhook?: string|null,
     *     auto_cleanup_type?: string|null,
     *     auto_cleanup_num?: int|null,
     *     run_on_one_server?: bool,
     *     run_in_background?: bool,
     *     frequencies?: array<int, array<string, mixed>>
     * } $input
     * @param  Task|int  $task
     */
    public function update(array $input, Task|int $task): Task
    {
        $task = $this->find($task);

        Updating::dispatch($input, $task);

        $task->fill(Arr::only($input, $task->getFillable()))->save();
        $task->afterSave($input);

        Updated::dispatch($task);

        return $task;
    }
```

Changes:
- Array shape added matching `store()` with `id` present.
- `$task` parameter tightened from untyped to `Task|int` (union type). The `@param` PHPDoc line is retained as a convenience annotation alongside the native type.
- Asymmetry with `store()` noted in the PHPDoc body: `id` appears in update but not store.

- [ ] **Step 4: Annotate `import()`**

Current (lines 194–216):

```php
    /**
     * Import tasks.
     */
    public function import($input): void
    {
        $this->cache()->forget('totem.tasks.all');
        $this->cache()->forget('totem.tasks.active');

        collect(json_decode(Arr::get($input, 'content')))
            ->each(function ($data) {
                ...
            });
    }
```

Replace with:

```php
    /**
     * Import tasks from a JSON payload.
     *
     * Shape diverges from store() / update(): import accepts a single
     * `content` key holding a JSON-encoded array of task objects. Each
     * decoded object is dispatched through store() or update() depending
     * on whether an existing task matches its id.
     *
     * @param  array{content: string}  $input
     */
    public function import(array $input): void
    {
        $this->cache()->forget('totem.tasks.all');
        $this->cache()->forget('totem.tasks.active');

        collect(json_decode(Arr::get($input, 'content')))
            ->each(function ($data) {
                $this->cache()->forget('totem.task.'.$data->id);

                $task = $this->find($data->id);

                if (is_null($task)) {
                    $this->store((array) $data);

                    return;
                }

                $this->update((array) $data, $task);
            });
    }
```

Changes:
- `$input` typed as `array` (native).
- Array shape `@param array{content: string}` — only one key expected.
- PHPDoc body documents the asymmetry with store/update shape.
- Method body unchanged (scope-guard AC4.6).

- [ ] **Step 5: Run tests**

```bash
$TEST_RUN
```

Expected: green. `tests/Feature/ImportTasksTest.php` and related tests exercise these paths. If the `Task|int` tightening on `update()` breaks any test, a caller was passing something else — investigate before widening.

- [ ] **Step 6: Commit**

```bash
git add src/Repositories/EloquentTaskRepository.php
git commit -m "types: annotate EloquentTaskRepository store/update/import array shapes"
```

---

## Task 7: Run Pint and resolve style violations

Goal: ensure the style checker reports zero violations per AC4.5.

**Files:**
- Possibly modify: any `src/` file Pint flags.

- [ ] **Step 1: Run Pint in test mode first**

```bash
vendor/bin/pint --test
```

Expected: zero violations. If violations surface, they are from the type-sweep edits (e.g., double blank lines after PHPDoc removal, trailing whitespace).

- [ ] **Step 2: Apply Pint fixes**

```bash
vendor/bin/pint
```

Review the diff:

```bash
git diff
```

All changes should be style-only (whitespace, PHPDoc alignment). If any semantic change appears, STOP and investigate — Pint shouldn't change semantics.

- [ ] **Step 3: Run tests again**

```bash
$TEST_RUN
```

Expected: green.

- [ ] **Step 4: Commit Pint fixes (if any diff)**

```bash
git add -u
git commit -m "style: apply Pint after type sweep"
```

Skip this step if `git diff` was empty.

---

## Task 8: Scope-guard verification (AC4.6)

Goal: prove PR 4 has no behavioral changes.

- [ ] **Step 1: Inspect the diff for non-type changes**

```bash
git diff main..feat/type-sweep --stat
git diff main..feat/type-sweep | head -200
```

Replace `main` with whatever the base branch is (`13.x`).

- [ ] **Step 2: Review every non-whitespace, non-PHPDoc change**

Walk the diff. For every change, answer: "is this a type declaration or PHPDoc change?"

- If YES: fine.
- If NO (e.g., a method body change, a condition tweak, a variable rename): EXTRACT into a separate PR.

Specifically look for:
- Method body edits (any line inside `{ ... }` that's not PHPDoc).
- New or removed conditional branches.
- Variable renames.
- Import additions that aren't required by a new type.

If any non-type change is found, revert that part:

```bash
# Revert a single file to the base branch version, preserving the rest
git checkout 13.x -- path/to/file.php
# Then re-apply ONLY the type changes manually
```

- [ ] **Step 3: Confirm with a grep sanity**

Compare method signatures only:

```bash
git diff 13.x..feat/type-sweep -- 'src/**/*.php' \
  | grep -E '^[-+].*function ' \
  | head -40
```

Every `-` / `+` line should be a signature change (type added, PHPDoc tightened). No `-` / `+` lines should be inside a method body.

---

## Task 9: Push and open PR

- [ ] **Step 1: Push**

```bash
git push -u origin feat/type-sweep
```

- [ ] **Step 2: Open the PR against 13.x**

Title: `Type sweep — add missing parameter/return types and array-shape PHPDoc`

Body:

```markdown
## Summary

Adds missing parameter and return type hints across `src/`. Promotes `@param $var` PHPDoc (no type) to real signatures where inferable. Adds array-shape PHPDoc to `EloquentTaskRepository::store()`, `update()`, and `import()`. Zero behavioral changes.

## Scope

- Events: constructor parameters and PHPDoc tightened. Example: `src/Events/Executed.php::__construct(Task $task, $started, $output)` → `(Task $task, float $started, string $output)`.
- Repository: three entry-point methods have array-shape `@param` PHPDoc reflecting actual call-site shapes (16 fillable fields on store/update; a single `content` key on import).
- Listeners, controllers, rules, traits, console commands, notifications, models: types added where signature gaps existed and the type was inferable from usage.

## Explicitly NOT in scope

- `readonly` class/property promotions (deferred).
- `mixed` parameters that are intentionally polymorphic (left as-is).
- PHPStan / Psalm baseline (deferred post-v13.0).
- Repository input shape normalization (behavioral; separate concern).
- Any method body, conditional, or rename changes.

## Scope-guard verification (AC4.6)

Per spec §4.3 scope-creep guard: PR 4's diff is restricted to type hints and PHPDoc. Reviewer assertion: walk the diff and confirm every `-`/`+` line is either:
- A parameter/return type addition or tightening.
- A PHPDoc block addition, refinement, or removal (of stale content).
- Whitespace adjusted by Pint.

Any line changing method body semantics is a review blocker and must be extracted.

## Array-shape examples

`store()` input shape (excerpt):
```php
@param array{
    description?: string,
    command?: string,
    parameters?: array<int|string, mixed>,
    expression?: string,
    ...
    frequencies?: array<int, array<string, mixed>>
} $input
```

Full shapes in `src/Repositories/EloquentTaskRepository.php`.

## Value proposition note

Per spec §4.4: this sweep provides documentation value and IDE autocomplete improvements. It does NOT provide static analysis enforcement (PHPStan is out-of-scope for v13.0). Incorrect types could drift silently until a later PHPStan introduction catches them.

## Test plan

- [x] Full Pest suite green after each file's changes.
- [x] `vendor/bin/pint --test` reports zero violations.
- [x] Diff scope-guard inspection — no method body changes.
- [ ] CI matrix green on all 8 blocking combos.

## Design spec

See `docs/plans/2026-04-21-totem-v13-release-design.md` §4. ACs AC4.1–AC4.6.
```

- [ ] **Step 3: Watch CI**

Expected: all 8 blocking combos green. Pint step passes. No new test failures.

If a combo fails on `prefer-lowest` but not `prefer-stable`, a tightened type may depend on an API that landed after the declared floor — raise the type to the union that the floor supports, or keep the type narrow and raise the `illuminate/*` floor in a separate PR.

---

## Task 10: After merge

- [ ] **Step 1: Fast-forward**

```bash
git checkout 13.x
git pull --ff-only origin 13.x
git branch -d feat/type-sweep
git push origin --delete feat/type-sweep
```

- [ ] **Step 2: Proceed to PR 5 (Release prep)**

See `docs/plans/2026-04-21-totem-v13-release-plan.md`.

---

## Summary: Acceptance Criteria Coverage

| AC    | Task(s)     | Verification                                                                              |
|-------|-------------|-------------------------------------------------------------------------------------------|
| AC4.1 | Tasks 3–5   | Reviewer inspects diff: no public/protected method in `src/` has an untyped inferable param. |
| AC4.2 | Tasks 3–5   | Reviewer inspects diff: no public/protected method in `src/` lacks an inferable return type. |
| AC4.3 | Task 6      | `src/Repositories/EloquentTaskRepository.php` has `@param array{...}` PHPDoc on store, update, import. Asymmetry documented in PHPDoc body. |
| AC4.4 | Tasks 3–7   | Pest suite green on CI blocking matrix after the sweep.                                   |
| AC4.5 | Task 7      | `vendor/bin/pint --test` reports zero violations.                                         |
| AC4.6 | Task 8      | Reviewer confirms diff contains only type/PHPDoc changes (no method-body edits).          |
