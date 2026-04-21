# Totem v13.0 Pest Migration (PR 3) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Migrate Totem's test suite from PHPUnit class-based style to Pest 3 functional style. Remove `phpunit/phpunit` from `require-dev`, add `pestphp/pest` + `pestphp/pest-plugin-laravel`. Preserve every assertion one-for-one. Forward-port the v12.0.2 popup fix and convert its regression test to Pest form. Update CI to run `vendor/bin/pest` instead of `vendor/bin/phpunit`.

**Architecture:** Three phases: (A) compat spike proves the target stack works, (B) mechanical file-by-file conversion of 18 test files with assertion-count parity verification, (C) CI command swap + Blade forward-port. The `tests/TestCase.php` base class stays exactly as is — Pest binds to it via `uses(TestCase::class)` in a new `tests/Pest.php`.

**Tech Stack:** PHP 8.3+, Laravel 12/13, Pest 3, Pest Plugin Laravel, Orchestra Testbench 10/11. Branch: `feat/pest-migration` off `13.x`. Prerequisite: PR 2 (v13 baseline) merged.

**Spec:** `docs/plans/2026-04-21-totem-v13-release-design.md` §3 (Section 3: PR 3). ACs AC3.1–AC3.5.

---

## File Structure

| File | Responsibility | Action |
|------|----------------|--------|
| `docs/plans/2026-04-21-totem-v13-release-design/spike-results.md` | Compat spike artifact: resolved versions, deprecations with disposition, compat issues. | Create (on spike branch, merged with PR 3) |
| `composer.json` | Add Pest deps, remove PHPUnit. | Modify (require-dev block) |
| `tests/Pest.php` | Pest configuration. Binds `TestCase` to feature suite. Registers test suite directories. | Create |
| `phpunit.xml` / `phpunit.xml.dist` | Legacy PHPUnit config. | Delete (whichever exists) |
| `tests/Feature/*.php` (16 files) | Individual feature tests. | Rewrite file-by-file into Pest syntax |
| `tests/Feature/Rules/*.php` (2 files) | Rule unit tests. | Rewrite file-by-file into Pest syntax |
| `tests/TestCase.php` | Base test case. | Unchanged |
| `tests/TestUser.php`, `tests/TotemUserFactory.php` | Fixtures. | Unchanged |
| `resources/views/tasks/view.blade.php` | Task-view Blade template. | Modify line 120 (forward-port popup fix) |
| `.github/workflows/laravel.yml` | CI workflow. | Modify one `run:` step (`vendor/bin/phpunit` → `vendor/bin/pest`) |

**Files to convert (18 total):**
- `tests/Feature/AuthTest.php`
- `tests/Feature/CacheStoreTest.php`
- `tests/Feature/CompileParametersTest.php`
- `tests/Feature/CreateTaskTest.php`
- `tests/Feature/EditTaskTest.php`
- `tests/Feature/ExportTasksTest.php`
- `tests/Feature/HasFrequenciesTest.php`
- `tests/Feature/ImportTasksTest.php`
- `tests/Feature/TaskExecutionTest.php`
- `tests/Feature/TaskRequestTest.php`
- `tests/Feature/TotemCommandsTest.php`
- `tests/Feature/TotemModelTest.php`
- `tests/Feature/UpcomingTasksTest.php`
- `tests/Feature/ViewDashboardTest.php`
- `tests/Feature/ViewTaskTest.php`
- `tests/Feature/VonageNotificationTest.php`
- `tests/Feature/Rules/CronExpressionRuleTest.php`
- `tests/Feature/Rules/JsonFileRuleTest.php`

---

# Phase A: Compat Spike (blocking prerequisite)

Goal: prove Pest 3 + Testbench 11 + Laravel 13 + PHP 8.3 is a working stack for this package BEFORE we commit to migration. Artifact output committed to `docs/plans/2026-04-21-totem-v13-release-design/spike-results.md`.

## Task 1: Spike — dry-run Pest against the existing suite

Goal: before any migration, install Pest alongside PHPUnit and see if the existing PHPUnit tests run cleanly under Pest 3's runner (Pest runs PHPUnit tests as a superset). Record everything.

**Files:**
- Create: `docs/plans/2026-04-21-totem-v13-release-design/spike-results.md`
- Temporary: `composer.json` (reverted after spike)

- [ ] **Step 1: Branch setup**

```bash
git checkout 13.x
git pull --ff-only origin 13.x
git checkout -b spike/pest-testbench-l13-compat
```

- [ ] **Step 2: Create spike artifact scaffolding**

```bash
mkdir -p docs/plans/2026-04-21-totem-v13-release-design
```

Create `docs/plans/2026-04-21-totem-v13-release-design/spike-results.md` with this template:

```markdown
# v13 Compat Spike Results

**Date:** YYYY-MM-DD
**Branch:** spike/pest-testbench-l13-compat
**Goal:** Verify Pest 3 + Testbench 11 + Laravel 13 + PHP 8.3 runs the existing PHPUnit test suite cleanly before committing to migration.

## Resolved Versions

_Filled in from `composer show` output after install._

- `laravel/framework`: ___
- `orchestra/testbench`: ___
- `pestphp/pest`: ___
- `pestphp/pest-plugin-laravel`: ___
- `phpunit/phpunit` (transitive via Pest): ___
- `illuminate/support`: ___

## Test Run Results

| Target combo | Runner | Pass | Fail | Skipped | Assertions | Runtime |
|--------------|--------|-----:|-----:|--------:|-----------:|--------:|
| PHP 8.3 × L13 × prefer-stable | PHPUnit (baseline) | | | | | |
| PHP 8.3 × L13 × prefer-stable | Pest 3 | | | | | |

## Deprecations Surfaced (Pest run)

| Deprecation | Origin (file:line) | Disposition | Notes |
|-------------|--------------------|-------------|-------|
| _(none, accept, fix-now, defer-with-ticket)_ |  |  |  |

## Compat Issues

| Issue | Runner | Workaround | Show-stopper? |
|-------|--------|------------|---------------|
|  |  |  |  |

## Pass Criteria (from spec §3.0)

- [ ] Zero failing tests on the target combo (Pest 3 + Testbench 11 + Laravel 13, prefer-stable, PHP 8.3).
- [ ] Every new deprecation has a disposition documented.
- [ ] No compat issue is flagged as a show-stopper.

## Conclusion

_Written after pass criteria are evaluated. Either "Spike passes, PR 3 may proceed" or "Spike fails — <reason>; PR 3 blocked pending <action>"._
```

- [ ] **Step 3: Install Pest alongside PHPUnit**

On the spike branch, temporarily add Pest to `composer.json` without removing PHPUnit:

```bash
composer require --dev pestphp/pest pestphp/pest-plugin-laravel --no-interaction --with-all-dependencies
```

Expected: composer resolves Pest 3.x (latest), Pest Plugin Laravel 3.x (latest), and Pest's bundled PHPUnit as a sub-dep.

If composer fails to resolve:
- Record the failure in the spike artifact under "Compat Issues" with disposition `show-stopper`.
- STOP — the spike has failed and PR 3 is blocked pending composer resolution plan.

- [ ] **Step 4: Record resolved versions**

```bash
composer show laravel/framework | head -3
composer show orchestra/testbench | head -3
composer show pestphp/pest | head -3
composer show pestphp/pest-plugin-laravel | head -3
composer show phpunit/phpunit | head -3
composer show illuminate/support | head -3
```

Paste the versions into the spike artifact's "Resolved Versions" section.

- [ ] **Step 5: Run baseline PHPUnit suite**

```bash
APP_ENV=testing vendor/bin/phpunit --log-junit /tmp/junit-phpunit-baseline.xml
```

Record pass/fail/assertion counts. Pest 3 will need to match or exceed the assertion count.

- [ ] **Step 6: Initialize Pest**

Pest 3 requires a `tests/Pest.php` config file. Create a minimal version for the spike (we'll expand it in Phase B):

Content of `tests/Pest.php`:

```php
<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a
| specific PHPUnit test case class. By default, that class is "TestCase".
| This base class is used by both Pest's functional API and the existing
| PHPUnit class-based tests during the migration.
|
*/

use Studio\Totem\Tests\TestCase;

uses(TestCase::class)->in('Feature');
```

Note: during the spike only, the existing PHPUnit tests still work because Pest 3 runs PHPUnit classes as a superset. The `uses()` directive above is a no-op for the class-based tests and becomes authoritative once Phase B converts them.

- [ ] **Step 7: Run Pest against the existing suite**

```bash
APP_ENV=testing vendor/bin/pest --log-junit /tmp/junit-pest-spike.xml
```

Record the results. Specifically capture:
- Number of tests run.
- Number of assertions.
- Any deprecation warnings (Pest surfaces these in its output).
- Runtime.

If Pest runs cleanly with the same pass count as the PHPUnit baseline, the stack works. If there are failures, record each one in "Compat Issues" with a workaround or show-stopper flag.

- [ ] **Step 8: Fill in the spike artifact**

Complete the `spike-results.md` tables and the conclusion section. If the pass criteria all hold, conclusion reads "Spike passes, PR 3 may proceed." Otherwise it reads "Spike fails — <reason>".

- [ ] **Step 9: Commit the spike artifact**

```bash
git add docs/plans/2026-04-21-totem-v13-release-design/spike-results.md
git commit -m "spike: record Pest 3 + Testbench 11 + Laravel 13 compat results"
```

- [ ] **Step 10: Revert composer changes on the spike branch**

The spike's `composer require` calls modified `composer.json` / `composer.lock`. We do NOT want those changes on the spike branch — those deps land in PR 3 as a proper migration. Revert:

```bash
git checkout composer.json composer.lock
composer install --prefer-dist --no-interaction
```

Verify:
```bash
git diff composer.json composer.lock
```

Expected: no diff. `pestphp/*` does NOT appear in `composer.json`.

- [ ] **Step 11: Push the spike branch and merge spike artifact to 13.x**

The spike artifact commit needs to be on `13.x` so PR 3 can reference it. Open a tiny PR that contains ONLY the spike-results commit:

```bash
git push -u origin spike/pest-testbench-l13-compat
gh pr create --base 13.x --title "spike: Pest 3 + Testbench 11 + Laravel 13 compat results" \
  --body "Spike artifact for v13 Pest migration prerequisite. See spec §3.0. Fast-merge — no code changes."
```

Merge it. Then:

```bash
git checkout 13.x
git pull --ff-only origin 13.x
```

Confirm `docs/plans/2026-04-21-totem-v13-release-design/spike-results.md` exists on `13.x`.

**Stop condition:** If the spike conclusion is "Spike fails," STOP. Open a discussion issue, update the spec to reflect the failure, and revisit PR 3 scoping. Do not proceed to Phase B.

---

# Phase B: Migrate the test suite

Goal: rewrite 18 test files from PHPUnit class-based syntax to Pest functional syntax. Remove `phpunit/phpunit`. Preserve assertion counts.

## Task 2: PR 3 branch setup and composer changes

- [ ] **Step 1: Create PR branch off current 13.x**

```bash
git checkout 13.x
git pull --ff-only origin 13.x
git checkout -b feat/pest-migration
```

Verify `docs/plans/2026-04-21-totem-v13-release-design/spike-results.md` is present (landed from Phase A):

```bash
ls -1 docs/plans/2026-04-21-totem-v13-release-design/
```

Expected: `spike-results.md`. If missing, Phase A did not complete; return to Task 1.

- [ ] **Step 2: Update composer.json require-dev**

Edit `composer.json`:

Before:
```json
"require-dev": {
    "laravel/slack-notification-channel": "^3.7",
    "laravel/vonage-notification-channel": "^3.3",
    "orchestra/testbench": "^10.0|^11.0",
    "phpunit/phpunit": "^11.0|^12.0"
},
```

After:
```json
"require-dev": {
    "laravel/slack-notification-channel": "^3.7",
    "laravel/vonage-notification-channel": "^3.3",
    "orchestra/testbench": "^10.0|^11.0",
    "pestphp/pest": "^3.0",
    "pestphp/pest-plugin-laravel": "^3.0"
},
```

(Exact minor version pins should match the versions resolved in the spike — e.g., `^3.7` if spike resolved to 3.7.x. Check `spike-results.md`.)

- [ ] **Step 3: Install and verify**

```bash
composer update --prefer-dist --no-interaction
composer show pestphp/pest | head -3
composer show phpunit/phpunit | head -3
```

Expected: `pestphp/pest` installed; `phpunit/phpunit` present as transitive (bundled by Pest) but NOT in `composer.json`.

- [ ] **Step 4: Commit composer changes**

```bash
git add composer.json composer.lock
git commit -m "chore: swap phpunit/phpunit for pestphp/pest and pest-plugin-laravel"
```

## Task 3: Create tests/Pest.php and delete PHPUnit config

- [ ] **Step 1: Create the Pest config**

Content of `tests/Pest.php`:

```php
<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a
| specific PHPUnit test case class. By default, that class is
| `Studio\Totem\Tests\TestCase`. Binding it via `uses()` means Pest-style
| `test()` / `it()` functions have access to TestCase's helpers
| (e.g., $this->signIn()).
|
*/

use Studio\Totem\Tests\TestCase;

uses(TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain
| conditions. The "expect()" function gives you access to a set of
| "expectations" methods that you can use to assert different things. Of
| course, you may extend the Expectation API at any time.
|
*/

// (No custom expectations yet; add if patterns emerge during migration.)

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code
| specific to your project that you don't want to repeat in every file.
| Here you can also expose helpers as global functions to help you to reduce
| the number of lines of code in your test files.
|
*/

// (No global test helpers yet.)
```

- [ ] **Step 2: Delete the PHPUnit config file**

```bash
ls phpunit.xml phpunit.xml.dist 2>/dev/null
```

Delete whichever exists:

```bash
# If phpunit.xml exists:
git rm phpunit.xml
# If phpunit.xml.dist exists:
git rm phpunit.xml.dist
```

Pest generates its own config at runtime from `tests/Pest.php` and `composer.json` autoload; no XML needed.

- [ ] **Step 3: Smoke-test the runner**

```bash
APP_ENV=testing vendor/bin/pest --version
APP_ENV=testing vendor/bin/pest --help | head -20
```

Expected: Pest prints its version (3.x) and the help output.

- [ ] **Step 4: Run the suite — pre-conversion baseline**

Pest 3 runs PHPUnit class-based tests as a superset, so the existing tests should pass under Pest before any conversion:

```bash
APP_ENV=testing vendor/bin/pest --log-junit /tmp/junit-pre-conversion.xml
```

Expected: same pass/fail counts as the spike's PHPUnit baseline. Record the per-file assertion counts:

```bash
# Parse junit XML for per-file assertion counts — helper script approach
php -r '
  $xml = simplexml_load_file("/tmp/junit-pre-conversion.xml");
  foreach ($xml->testsuite->testsuite ?? $xml->testsuite as $suite) {
    printf("%-60s tests=%-3d assertions=%-4d\n",
      (string) $suite["name"],
      (int) $suite["tests"],
      (int) $suite["assertions"]);
  }
'
```

Save this output as `/tmp/assertion-baseline.txt` — Phase B's parity check uses it.

- [ ] **Step 5: Commit the Pest config**

```bash
git add tests/Pest.php
git rm phpunit.xml* 2>/dev/null || true
git add -u
git commit -m "test: add tests/Pest.php config and remove legacy phpunit.xml"
```

## Task 4: File-by-file conversion — the pattern

Goal: establish the conversion pattern with the simplest file, then apply it systematically.

Each file conversion follows the same procedure:
1. Read the existing PHPUnit file.
2. Rewrite in Pest syntax.
3. Run the converted file's tests under Pest.
4. Assert the assertion count matches (or exceeds, per policy) the pre-conversion baseline for this file.
5. Commit the single-file change.

**Conversion rules (apply uniformly):**

- PHPUnit `class XyzTest extends TestCase { ... }` → Pest top-level `test('description', function () { ... });` / `it('description', function () { ... });`.
- `public function test_some_name()` → `test('some name', function () { ... });` (convert snake_case method names to space-separated human-readable descriptions).
- Method bodies move verbatim into the closure body, BUT:
  - `$this->assertSame(...)` → `expect($actual)->toBe($expected)` (where it reads naturally) OR keep `$this->assertSame(...)` (Pest supports both; we keep PHPUnit assertions when they more directly mirror the original to minimize reviewer cognitive load).
  - Keep `$this->signIn()`, `$this->get(...)`, `$this->artisan(...)` etc. — Pest binds `$this` to the TestCase.
- `@dataProvider` methods → Pest `dataset('name', [...])` + `test('...', function ($a, $b) { ... })->with('name')`. Only refactor if a data provider exists; don't invent datasets.
- `setUp()` method logic → use Pest's `beforeEach()` at the top of the file. If the class didn't define `setUp()`, skip.
- `tearDown()` method → Pest `afterEach()`.

**Keep it mechanical.** Do NOT refactor test logic, do NOT rename assertions for style, do NOT collapse similar tests into datasets unless they ALREADY used a data provider.

- [ ] **Step 1: Read `tests/Feature/AuthTest.php`** (simplest file, good pattern establishment)

```bash
cat tests/Feature/AuthTest.php
```

Assume it contains 1-3 simple tests.

- [ ] **Step 2: Rewrite `tests/Feature/AuthTest.php` in Pest form**

Example transformation (adapt based on actual file content):

Before (PHPUnit):
```php
<?php

namespace Studio\Totem\Tests\Feature;

use Studio\Totem\Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_can_view_dashboard_when_authorized()
    {
        $this->signIn();
        $response = $this->get(route('totem.dashboard'));
        $response->assertStatus(200);
    }

    public function test_cannot_view_dashboard_when_unauthorized()
    {
        $response = $this->get(route('totem.dashboard'));
        $response->assertStatus(403);
    }
}
```

After (Pest):
```php
<?php

test('can view dashboard when authorized', function () {
    $this->signIn();
    $response = $this->get(route('totem.dashboard'));
    $response->assertStatus(200);
});

test('cannot view dashboard when unauthorized', function () {
    $response = $this->get(route('totem.dashboard'));
    $response->assertStatus(403);
});
```

Note: no namespace, no class, no `use` import for TestCase (the `uses()` directive in `tests/Pest.php` binds it globally to the Feature directory).

- [ ] **Step 3: Run the converted file**

```bash
APP_ENV=testing vendor/bin/pest tests/Feature/AuthTest.php --log-junit /tmp/junit-auth-converted.xml
```

Expected: same test count and assertion count as the pre-conversion baseline for this file.

Check assertion count:

```bash
php -r '
  $xml = simplexml_load_file("/tmp/junit-auth-converted.xml");
  printf("assertions: %d\n", (int) $xml->testsuite["assertions"]);
'
```

Compare to the baseline recorded in `/tmp/assertion-baseline.txt`.

- [ ] **Step 4: Commit the single-file conversion**

```bash
git add tests/Feature/AuthTest.php
git commit -m "test: convert AuthTest to Pest syntax"
```

This pattern — read, rewrite, run, commit — is repeated for each file in Task 5.

## Task 5: Convert the remaining 17 files

**Order of conversion** (start with simplest to de-risk the pattern, end with the file containing the popup-fix regression test):

1. `tests/Feature/Rules/CronExpressionRuleTest.php` — likely simple rule tests
2. `tests/Feature/Rules/JsonFileRuleTest.php` — likely simple rule tests
3. `tests/Feature/CacheStoreTest.php`
4. `tests/Feature/CompileParametersTest.php`
5. `tests/Feature/TotemModelTest.php`
6. `tests/Feature/TaskRequestTest.php`
7. `tests/Feature/HasFrequenciesTest.php`
8. `tests/Feature/UpcomingTasksTest.php`
9. `tests/Feature/CreateTaskTest.php`
10. `tests/Feature/EditTaskTest.php`
11. `tests/Feature/ViewDashboardTest.php`
12. `tests/Feature/ImportTasksTest.php`
13. `tests/Feature/ExportTasksTest.php`
14. `tests/Feature/TotemCommandsTest.php`
15. `tests/Feature/TaskExecutionTest.php`
16. `tests/Feature/VonageNotificationTest.php`
17. `tests/Feature/ViewTaskTest.php` — contains the popup regression test (see Task 6 for forward-port handling)

For each file in the list above:

- [ ] **Step 1: Read** — `cat tests/Feature/<FileName>.php`.
- [ ] **Step 2: Rewrite** — apply the conversion rules from Task 4. Preserve every assertion.
- [ ] **Step 3: Run** — `APP_ENV=testing vendor/bin/pest tests/Feature/<FileName>.php --log-junit /tmp/junit-<filename>.xml`.
- [ ] **Step 4: Verify assertion count** — compare to `/tmp/assertion-baseline.txt`; must be ≥ baseline for this file. If lower, justify in commit message (e.g., "Pest `expect(...)->toBe(...)` collapses two PHPUnit assertions into one — semantically equivalent") OR rewrite the test to preserve the count.
- [ ] **Step 5: Run full suite** — `APP_ENV=testing vendor/bin/pest` to confirm no regressions in other files.
- [ ] **Step 6: Commit** — `git commit -m "test: convert <FileName> to Pest syntax"`.

**Spot-check policy (AC3.1 + spec §3.3):**

- For every file **under 50 source lines**, review the converted file by eye for semantic equivalence after Step 2. Record in a scratch list (which files reviewed).
- For every file **50 lines or longer**, review at least 25% of the converted tests by eye, focusing on mocks, expectations with complex setup, data providers converted to datasets.

Keep a running list of reviewed tests in `/tmp/spot-check-log.txt`. This feeds the PR body.

## Task 6: Forward-port the v12.0.2 popup fix and convert its test

Goal: bring the `@json()` → `Js::from()` fix from the `12.x` branch onto `13.x`, and convert the PHPUnit regression test to Pest form.

**Files:**
- Modify: `resources/views/tasks/view.blade.php` line 120
- Modify: `tests/Feature/ViewTaskTest.php` (this is step 17 in the Task 5 conversion list — handle here instead)

- [ ] **Step 1: Apply the Blade change**

Change `resources/views/tasks/view.blade.php` line 120 from:

```blade
                            <task-output :output="@json($result->result)"></task-output>
```

To:

```blade
                            <task-output :output="{{ \Illuminate\Support\Js::from($result->result) }}"></task-output>
```

- [ ] **Step 2: Convert ViewTaskTest.php to Pest (including the regression test)**

Read the current file (which, after PR 1 / v12.0.2 is forward-ported, should include the `test_output_attribute_roundtrips_edge_cases` method):

```bash
cat tests/Feature/ViewTaskTest.php
```

Rewrite as:

```php
<?php

use Studio\Totem\Result;
use Studio\Totem\Task;

test('user can view task', function () {
    $this->signIn();
    $task = Task::factory()->create();
    $response = $this->get(route('totem.task.view', ['totemTask' => $task]));
    $response->assertStatus(200);
    $response->assertSee($task->description);
    $response->assertSee('Studio\Totem\Console\Commands\ListSchedule');
    $response->assertSee($task->expression);
});

test('guest can not view task', function () {
    $task = Task::factory()->create();
    $response = $this->get(route('totem.task.view', ['totemTask' => $task]));
    $response->assertStatus(403);
});

/**
 * Regression test for the empty-output-popup bug. See v12.0.2 release notes
 * and spec §1.3 for the full rationale. Briefly: @json() produces JSON
 * whose outer `"` delimiters collide with the HTML attribute's own `"`
 * wrappers, making the Vue :output prop arrive empty. Fix is Js::from().
 *
 * This test renders the task-view page with a result whose output contains
 * characters known to break attribute encoding, parses the response with
 * DOMDocument, extracts the :output attribute, decodes it, and asserts
 * round-trip equality.
 *
 * Negative control: reverting the Blade change to @json() MUST fail this
 * test.
 *
 * Null bytes are excluded from the corpus — the task_results.result column
 * does not accept them.
 */
test('output attribute roundtrips edge cases', function () {
    $corpus = implode("\n", [
        'double "quotes" inside a line',
        'backslash \\ and forward slash /',
        'less-than <script>alert(1)</script> tags',
        'apostrophe \'s and ampersand & symbols',
        'emoji 🔥 and four-byte UTF-8 𝕏',
        "CR\r LF newline above, tab\there, and nothing fancy",
    ]);

    $this->signIn();

    $task = Task::factory()->create();
    Result::factory()->create([
        'task_id' => $task->id,
        'result' => $corpus,
        'duration' => 1234,
        'ran_at' => now(),
    ]);

    $response = $this->get(route('totem.task.view', ['totemTask' => $task]));
    $response->assertStatus(200);

    $decoded = extractTaskOutputAttribute($response->getContent());

    expect($decoded)->toBe(
        $corpus,
        'Round-tripped :output attribute must exactly equal the source result string.'
    );
});

/**
 * Extract the first <task-output> element's :output attribute from the
 * response HTML and decode it back to the original string. Handles both
 * the Js::from() shape (`JSON.parse('"..."')`) and the legacy @json()
 * shape (bare JSON literal, which would mean the fix was reverted).
 */
function extractTaskOutputAttribute(string $html): string
{
    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $nodes = $dom->getElementsByTagName('task-output');
    expect($nodes->length)->toBeGreaterThan(
        0,
        '<task-output> element must be present in the task-view response.'
    );

    /** @var DOMElement $node */
    $node = $nodes->item(0);
    $attr = $node->getAttribute(':output');
    expect($attr)->not->toBe(
        '',
        ':output attribute must not be empty — empty means the Blade binding broke again.'
    );

    if (preg_match("/^JSON\\.parse\\('(.+)'\\)$/s", $attr, $m)) {
        $inner = str_replace('\\u0027', "'", $m[1]);
        $json = json_decode($inner, true, 512, JSON_THROW_ON_ERROR);

        return (string) $json;
    }

    $json = json_decode($attr, true);

    return (string) $json;
}
```

The `extractTaskOutputAttribute()` function is a top-level function in this test file (not a method on a class). Pest allows this. Alternatively, move it to `tests/Pest.php` as a shared helper — but keeping it local preserves the close coupling to this test.

- [ ] **Step 3: Run ViewTaskTest under Pest**

```bash
APP_ENV=testing vendor/bin/pest tests/Feature/ViewTaskTest.php
```

Expected: 3 tests pass. The `output attribute roundtrips edge cases` test verifies the forward-ported Blade fix works.

- [ ] **Step 4: Run the full suite**

```bash
APP_ENV=testing vendor/bin/pest
```

Expected: all tests pass.

- [ ] **Step 5: Commit**

```bash
git add resources/views/tasks/view.blade.php tests/Feature/ViewTaskTest.php
git commit -m "fix: forward-port v12.0.2 popup fix to 13.x with Pest regression test"
```

## Task 7: Assertion parity verification

Goal: prove no test coverage was lost in the conversion. Per AC3.1.

- [ ] **Step 1: Run the full Pest suite with junit output**

```bash
APP_ENV=testing vendor/bin/pest --log-junit /tmp/junit-pest-final.xml
```

- [ ] **Step 2: Parse per-file assertion counts**

```bash
php -r '
  $xml = simplexml_load_file("/tmp/junit-pest-final.xml");
  foreach ($xml->testsuite->testsuite ?? $xml->testsuite as $suite) {
    printf("%-60s tests=%-3d assertions=%-4d\n",
      (string) $suite["name"],
      (int) $suite["tests"],
      (int) $suite["assertions"]);
  }
' > /tmp/assertion-final.txt
```

- [ ] **Step 3: Compare to baseline**

Diff `/tmp/assertion-baseline.txt` against `/tmp/assertion-final.txt`. For each file, Pest count must be ≥ baseline count.

```bash
diff /tmp/assertion-baseline.txt /tmp/assertion-final.txt
```

Expected outcomes:
- **Same count:** fine; pure mechanical conversion.
- **Higher count (Pest > baseline):** fine; Pest `expect()` chains often register more assertions for the same logic.
- **Lower count (Pest < baseline):** BLOCKER. For each such file, either:
  - Identify why and justify in the PR body (e.g., dataset collapsed identical assertions), OR
  - Rewrite the test to preserve the count.

- [ ] **Step 4: Record parity results in the PR body draft**

Append to a scratch file `/tmp/pr-3-parity.md`:

```markdown
## Assertion Parity (per file)

| File | Baseline (PHPUnit) | After (Pest) | Delta | Notes |
|------|-------------------:|-------------:|------:|-------|
| (one row per file) |  |  |  |  |
```

This goes into the PR body.

## Task 8: Switch CI command to Pest

**Files:**
- Modify: `.github/workflows/laravel.yml` — one `run:` step

- [ ] **Step 1: Read the workflow**

```bash
grep -n 'vendor/bin/phpunit' .github/workflows/laravel.yml
```

Expected: exactly one match, on the last step of the job:

```yaml
      - name: Execute tests (PHPUnit)
        # PR 2 uses PHPUnit; PR 3 replaces this with `vendor/bin/pest`.
        run: vendor/bin/phpunit
```

- [ ] **Step 2: Update the step**

Replace with:

```yaml
      - name: Execute tests (Pest)
        run: vendor/bin/pest
```

Remove the transitional comment that PR 2 added.

- [ ] **Step 3: Verify**

```bash
grep -A2 'Execute tests' .github/workflows/laravel.yml
grep -c 'vendor/bin/phpunit' .github/workflows/laravel.yml
grep -c 'vendor/bin/pest' .github/workflows/laravel.yml
```

Expected: `vendor/bin/phpunit` appears 0 times; `vendor/bin/pest` appears 1 time.

- [ ] **Step 4: Commit**

```bash
git add .github/workflows/laravel.yml
git commit -m "ci: switch test runner from PHPUnit to Pest"
```

---

# Phase C: Push and release

## Task 9: Push the branch and open the PR

- [ ] **Step 1: Push**

```bash
git push -u origin feat/pest-migration
```

- [ ] **Step 2: Open the PR against 13.x**

Title: `Migrate test suite to Pest 3 + forward-port v12.0.2 popup fix`

Body:

```markdown
## Summary

Migrates the Totem test suite from PHPUnit class-based syntax to Pest 3 functional syntax. Drops `phpunit/phpunit` from `require-dev` (Pest bundles it transitively). Converts 18 test files one-by-one with per-file assertion-count parity verification. Also forward-ports the v12.0.2 empty-output-popup fix to `13.x`.

## Spike artifact

`docs/plans/2026-04-21-totem-v13-release-design/spike-results.md` — committed separately to 13.x before this PR opened. Spike conclusion: passes. Resolved versions pinned in the composer floors here.

## Dependency changes

- Remove: `phpunit/phpunit`.
- Add: `pestphp/pest ^3.0`, `pestphp/pest-plugin-laravel ^3.0`.
- `orchestra/testbench` unchanged (already at `^10.0|^11.0` from PR 2).

## Converted files

| File | Baseline assertions | Pest assertions | Notes |
|------|--------------------:|----------------:|-------|
| _(18 rows — populate from /tmp/pr-3-parity.md)_ |  |  |  |

## Spot-check log

Files reviewed by eye for semantic equivalence (per spec §3.3):

- _(List files reviewed, including 100% of files under 50 lines and ≥25% of larger files.)_

## Forward-ported fix

`resources/views/tasks/view.blade.php:120`: `@json($result->result)` → `{{ \Illuminate\Support\Js::from($result->result) }}`. Same fix as v12.0.2 on the `12.x` branch. Regression test (Pest form) included in `tests/Feature/ViewTaskTest.php::'output attribute roundtrips edge cases'`.

## CI matrix

All 8 blocking combos (PHP 8.3/8.4 × Laravel 12/13 × prefer-lowest/prefer-stable) now run `vendor/bin/pest`. 4 experimental 8.5 combos likewise. Matrix structure from PR 2 unchanged.

## Test plan

- [x] Spike artifact committed to 13.x before this PR opened.
- [x] `composer update` resolves cleanly.
- [x] Each file conversion passes in isolation (`vendor/bin/pest tests/Feature/<file>.php`).
- [x] Full suite passes after all conversions.
- [x] Per-file assertion count ≥ baseline (parity table above).
- [x] Forward-ported popup fix passes regression test.
- [ ] CI matrix green on all 8 blocking combos.

## Design spec

See `docs/plans/2026-04-21-totem-v13-release-design.md` §3. ACs AC3.1–AC3.5.
```

- [ ] **Step 3: Watch CI**

All 8 blocking combos must pass. Pest 3 runtime is typically comparable to or faster than PHPUnit. If any combo fails, diagnose; likely causes:

- Deprecation warning escalated to error on a specific PHP/Laravel combo — document in spike addendum or fix in-PR.
- Dataset conversion subtly changed semantics — spot-check the affected file.
- `tests/Pest.php` path scope wrong — ensure `uses(TestCase::class)->in('Feature')` covers all test directories including `Feature/Rules`.

## Task 10: After merge

- [ ] **Step 1: Fast-forward local 13.x**

```bash
git checkout 13.x
git pull --ff-only origin 13.x
```

- [ ] **Step 2: Delete local branch**

```bash
git branch -d feat/pest-migration
git push origin --delete feat/pest-migration
```

- [ ] **Step 3: Proceed to PR 4**

See `docs/plans/2026-04-21-totem-v13-type-sweep-plan.md`. PR 4 branches off current `13.x` and can land in parallel with PR 3 once PR 2 is merged (per spec dependency graph), BUT since PR 3 changes the test runner, PR 4's verification step must use `vendor/bin/pest`. In practice, land PR 3 first to avoid rebasing PR 4.

---

## Summary: Acceptance Criteria Coverage

| AC    | Task(s)     | Verification                                                                                      |
|-------|-------------|---------------------------------------------------------------------------------------------------|
| AC3.1 | Task 7      | `/tmp/assertion-final.txt` ≥ `/tmp/assertion-baseline.txt` per file; parity table in PR body.     |
| AC3.2 | Task 9      | CI blocking matrix (8 combos) green under `vendor/bin/pest`.                                      |
| AC3.3 | Task 6      | `tests/Feature/ViewTaskTest.php::'output attribute roundtrips edge cases'` passes.                |
| AC3.4 | Task 2      | `composer.json` `require-dev` has no `phpunit/phpunit` line; `grep phpunit/phpunit composer.json` returns nothing. |
| AC3.5 | Task 1, 9   | `docs/plans/2026-04-21-totem-v13-release-design/spike-results.md` exists on 13.x and is referenced in the PR body. |
