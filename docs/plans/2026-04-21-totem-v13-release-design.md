# Laravel Totem v13 Release Design

**Date:** 2026-04-21 (revised after /dg review round 2)
**Goal:** Ship `v12.0.2` (empty-output-popup hotfix) from `12.x`, then `v13.0.0` from `13.x` with Laravel 13 support, a Pest 3 migration, and a moderate type/PHPDoc sweep. Drop Laravel 10/11 and PHP 8.2. Browser testing infrastructure is **deferred to v13.1**.
**Approach:** Five sequenced PRs shipping v13.0 (one on `12.x`, four on `13.x`), plus a tracked follow-up for browser tests.
**Risk Classification:** **MEDIUM** — framework version floor bump + test-framework migration. Behavior-preserving for Totem's own code. Gates: (a) Pest 3 / Testbench 11 / Laravel 13 compat spike must pass before PR 3 lands; (b) `composer audit` exposure analysis documented before v13.0.0 tag; (c) AC→test mapping complete before any implementation PR merges.

---

## Ship Strategy

Five PRs shipping v13.0; one follow-up PR scheduled for v13.1:

| # | Branch | Title | Depends on | Release |
|---|--------|-------|------------|---------|
| 1 | `hotfix/empty-output-popup` off `12.x` | Fix empty output popup | — | v12.0.2 |
| 2 | `feat/v13-baseline` off `13.x` | Drop L10/11 + PHP 8.2, add L13, bump CI matrix | — | v13.0.0 |
| 3 | `feat/pest-migration` off `13.x` | PHPUnit → Pest 3; port popup fix to 13.x | PR 2 + compat spike artifact | v13.0.0 |
| 4 | `feat/type-sweep` off `13.x` | Param/return types + array shape PHPDoc across `src/` | PR 2 (parallel with PR 3) | v13.0.0 |
| 5 | `release/v13.0.0` off `13.x` | README / CHANGELOG polish; tag v13.0.0 | PRs 3–4 | v13.0.0 |
| **F** | `feat/browser-tests` off `13.x` | **DEFERRED to v13.1.** Pest browser plugin + Playwright in CI + popup + smoke regressions | PR 3 merged | v13.1.0 |

**Follow-up requirements for PR F (browser tests, v13.1):** A Jira or GitHub issue MUST exist before v13.0.0 ships, with a named owner and target date. The v13.0.0 release notes MUST include a line explicitly stating "browser tests deferred to v13.1 — see [TICKET-###]."

**The popup fix travels twice:** PR 1 lands it on 12.x with a server-side Pest-equivalent PHPUnit regression test. PR 3 ports the fix and converts the test to Pest on 13.x. The true browser-level regression lands with PR F in v13.1.

---

## Section 1: PR 1 — Popup Fix on 12.x (v12.0.2)

### 1.1 Root Cause

`resources/views/tasks/view.blade.php:120` uses `@json()` to pass the task result into a Vue component prop:

```blade
<task-output :output="@json($result->result)"></task-output>
```

`@json()` expands to `json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)`.

**Evidence (verify directly):**
```
$ php -r 'echo json_encode("hello", JSON_HEX_QUOT) . PHP_EOL;'
"hello"

$ php -r 'echo json_encode("say \"hi\"", JSON_HEX_QUOT) . PHP_EOL;'
"say "hi""
```

`JSON_HEX_QUOT` escapes only the `"` characters that appear inside JSON string content (where they would otherwise be backslash-escaped). The JSON structural delimiters remain as literal `"` because escaping them would break the JSON grammar — `"hello"` would become `"hello"`, which is not a valid JSON string.

**Consequence in an HTML attribute:** The rendered markup becomes `:output=""hello""`, which browsers parse as `:output=""` (empty) followed by orphan text. The Vue prop arrives empty, so `{{ output }}` inside the modal renders blank. The user sees the modal frame (header, close button, footer) but an empty `<pre>`.

`@json()` was never designed for HTML attribute context — its intended use is inside `<script>` blocks or mustache `{{ }}` output where Blade's own HTML-escaping makes the `"` characters safe. `Js::from()` is Laravel's purpose-built helper for attribute-context JS expressions.

### 1.2 Fix

Replace `@json()` with `Js::from()` at `resources/views/tasks/view.blade.php:120`:

```blade
{{-- Before --}}
<task-output :output="@json($result->result)"></task-output>

{{-- After --}}
<task-output :output="{{ \Illuminate\Support\Js::from($result->result) }}"></task-output>
```

`Js::from()` produces single-quoted JSON-parsed expressions (`JSON.parse('\"hello\"')`) that are safe inside double-quoted attributes. HTML-special characters are escaped via `JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_TAG`.

### 1.3 Regression Test

New PHPUnit test in `tests/Feature/ViewTaskTest.php`:

1. **Fixture:** Create a task + result where `result.result` contains the following edge-case corpus:
   - `double "quotes" inside`
   - `backslash \ and forward slash /`
   - `less-than <script>alert(1)</script> tags`
   - `apostrophe 's and ampersand & symbols`
   - `emoji 🔥 and four-byte UTF-8 𝕏`
   - `CR\r LF\n tab\t escape sequences`
2. **Act:** Authenticate as a Totem user and GET the task-view route.
3. **Assert (round-trip):**
   - Parse the response HTML with `DOMDocument`.
   - Locate the `<task-output>` element's `:output` attribute value.
   - Extract the JS expression, evaluate its string content (strip the `JSON.parse(...)` wrapper if present, or use `json_decode` directly on the serialized form).
   - Assert the decoded string exactly equals the source corpus.
4. **Negative control:** Reverting the fix (switching back to `@json()`) MUST fail this test.

Null bytes are deliberately excluded from the corpus: the database column does not accept them (UTF-8 text column), so they are unreachable in production. This exclusion is documented in the test PHPDoc.

### 1.4 Acceptance Criteria

- **AC1.1:** `tests/Feature/ViewTaskTest.php::test_output_attribute_roundtrips_edge_cases` passes with the fix applied.
- **AC1.2:** The same test fails deterministically when the `Js::from()` change is reverted to `@json()`. Negative control must be documented in the test.
- **AC1.3:** Existing PHPUnit suite on 12.x stays green across the 12.x CI matrix.
- **AC1.4:** `CHANGELOG.md` has a new entry under a `## [12.0.2] - YYYY-MM-DD` heading (Keep a Changelog format, matching the existing repo style where `## vX.Y.Z - MM/DD/YYYY` has been used — the v13 release promotes the repo to `## [X.Y.Z] - YYYY-MM-DD` bracketed form consistent with keepachangelog.com/en/1.1.0). The v12.0.2 entry contains a `### Fixed` subsection describing the bug symptom (empty output popup), root cause (`@json` attribute encoding), and fix (`Js::from()`). Section is moved out of an Unreleased block if one exists.

### 1.5 Release

Tag `v12.0.2` after merge. No other code changes ship on 12.x.

---

## Section 2: PR 2 — v13 Baseline on 13.x

### 2.1 Composer Constraint Changes

Laravel 13 status verified via Packagist: `v13.0.0` released; `v13.6.0` latest at design time (2026-04-21). Laravel 12's latest is `v12.56.0`.

**`require`** — floors pinned to the first stable minor of each supported Laravel major:
```json
"php": "^8.3",
"ext-json": "*",
"illuminate/bus": "^12.0|^13.0",
"illuminate/console": "^12.0|^13.0",
"illuminate/contracts": "^12.0|^13.0",
"illuminate/database": "^12.0|^13.0",
"illuminate/events": "^12.0|^13.0",
"illuminate/notifications": "^12.0|^13.0"
```

**`require-dev`** (PR 2 retains phpunit; PR 3 replaces with Pest):
```json
"laravel/slack-notification-channel": "^3.7",
"laravel/vonage-notification-channel": "^3.3",
"orchestra/testbench": "^10.0|^11.0",
"phpunit/phpunit": "^11.0|^12.0"
```

**Laravel 13 upgrade items verified.** Each bullet below links to the specific Laravel 13 upgrade guide section that was walked, OR to the release notes / source file that demonstrates the guarantee. The PR 2 body must include these citations verbatim — a single link to the upgrade guide page is **not** sufficient.

Citation format: `https://laravel.com/docs/13.x/upgrade#<section-anchor>` for guide sections, or `https://github.com/laravel/framework/blob/13.x/<path>` for source files.

- **Service-provider auto-discovery** (`extra.laravel.providers` block in `composer.json` unchanged): walked `docs/13.x/upgrade.md#package-discovery` (anchor name stable across Laravel majors); no changes to how `composer.json` providers are discovered.
- **`Illuminate\Support\Js`** (used in `resources/views/tasks/view.blade.php:120` after PR 3 ports the popup fix): walked `docs/13.x/upgrade.md#javascript-and-css-scaffolding` / `#strings` sections; cross-referenced against `https://github.com/laravel/framework/blob/13.x/src/Illuminate/Support/Js.php` for signature parity with 12.x.
- **`Illuminate\Console\Scheduling\Schedule::command()` + `->thenWithOutput()`** (used in `src/Providers/TotemServiceProvider.php::scheduleTotemTasks`): walked `docs/13.x/upgrade.md#scheduling`; cross-referenced against `https://github.com/laravel/framework/blob/13.x/src/Illuminate/Console/Scheduling/Event.php` for `thenWithOutput` signature parity and `ensureOutputIsBeingCaptured` behavior.
- **Notification channel contracts** (used in `src/Notifications/TaskCompleted.php`): walked `docs/13.x/upgrade.md#notifications`; `MailMessage`, `SlackMessage`, `VonageMessage` signatures unchanged.

**Citation fidelity requirement:** If any anchor above does not resolve at PR 2 time (upgrade guide anchors may change during a Laravel minor cycle), the bullet must be rewritten to cite the current anchor, and the change documented in the PR body. Stale anchors are a PR 2 blocker.

If any of these assumptions prove wrong during the compat spike (see §3.0), the constraint will be raised accordingly and documented.

### 2.2 CI Matrix

`.github/workflows/laravel.yml` — job block (compilable, copy-paste ready):

```yaml
jobs:
  tests:
    runs-on: ${{ matrix.os }}
    continue-on-error: ${{ matrix.experimental == true }}
    strategy:
      fail-fast: false
      matrix:
        os: [ubuntu-latest]
        php: ['8.3', '8.4']
        laravel: ['12.*', '13.*']
        dependencies: [prefer-lowest, prefer-stable]
        experimental: [false]
        include:
          # PHP 8.5 non-blocking smoke — 4 combos, all experimental
          - { os: ubuntu-latest, php: '8.5', laravel: '12.*', dependencies: prefer-lowest,  experimental: true }
          - { os: ubuntu-latest, php: '8.5', laravel: '12.*', dependencies: prefer-stable,  experimental: true }
          - { os: ubuntu-latest, php: '8.5', laravel: '13.*', dependencies: prefer-lowest,  experimental: true }
          - { os: ubuntu-latest, php: '8.5', laravel: '13.*', dependencies: prefer-stable,  experimental: true }
    name: "PHP ${{ matrix.php }} / L${{ matrix.laravel }} / ${{ matrix.dependencies }}${{ matrix.experimental && ' (experimental)' || '' }}"
    steps:
      # ... setup-php, cache, composer update, etc.
      - name: Install dependencies
        run: composer update --${{ matrix.dependencies }} --prefer-dist --no-interaction
      - name: Verify illuminate floor (prefer-lowest only)
        if: matrix.dependencies == 'prefer-lowest'
        run: |
          composer show illuminate/support | grep -E '^versions\b'
          # Fails the lane if resolved version is below the declared floor.
          php -r '
            $v = trim(shell_exec("composer show illuminate/support 2>/dev/null | awk \"/^versions/ {print \\$3}\""));
            $min = "12.0.0";
            if (version_compare(ltrim($v, "v"), $min, "<")) {
              fwrite(STDERR, "illuminate/support resolved to $v, below declared floor $min\n");
              exit(1);
            }
          '
      - name: Execute tests
        run: vendor/bin/phpunit   # PR 2 uses PHPUnit; PR 3 replaces this with vendor/bin/pest
```

**Blocking lanes** (merge gates): PHP {8.3, 8.4} × Laravel {12.*, 13.*} × dependencies {prefer-lowest, prefer-stable} = 8 combos.

**Non-blocking lanes** (experimental): PHP 8.5 × Laravel {12.*, 13.*} × dependencies {prefer-lowest, prefer-stable} = 4 combos; `continue-on-error: true` via the `matrix.experimental` flag.

**Test runner changes across PRs:** PR 2 leaves the CI command as `vendor/bin/phpunit`. PR 3 replaces it with `vendor/bin/pest` (AC3.2) and deletes `phpunit.xml`. No other CI structural changes across PRs.

**PHP 8.5 promotion criteria** — move from `experimental: true` to `experimental: false` when EITHER of these holds:
- (A) Zero net-new deprecation warnings surfaced by our test suite on 8.5 across two consecutive weekly scheduled runs, AND full suite passes on 8.5 for four consecutive CI runs.
- (B) 30 days have elapsed since Laravel's official announcement of PHP 8.5 support (typically via a `laravel/framework` minor release note).

The promotion must be cited in the CHANGELOG entry that performs the promotion.

`prefer-lowest` ensures the composer constraint floor advertised in §2.1 actually installs and tests clean. The floor-verification step shown above makes drift explicit — catches the classic "composer.json says `^12.0` but the lock resolves a later minor because a transitive dep requires it" failure mode.

### 2.3 README Compatibility Matrix

Replace current README matrix with all supported combinations across maintained Totem majors:

| Laravel | Totem   | Notes                                     |
|---------|---------|-------------------------------------------|
| 13.x    | 13.x    | v13 adds L13 support                      |
| 12.x    | 13.x    | Recommended: upgrade from 12.x for Pest migration and improved type coverage (AC-adjacent behavior unchanged) |
| 12.x    | 12.x    | Maintained                                |
| 11.x    | 12.x    | Maintained                                |
| 11.x    | 11.x    | Maintained                                |
| 10.x    | 11.x    | Maintained                                |

Requirements section updated to PHP 8.3+ and Laravel 12.x or 13.x for v13. v11.x and v12.x maintenance branches retain their original requirements.

**Upgrade rationale for L12 users on Totem 13.x:** v13 is behavior-compatible for L12 users but delivers (a) the Pest 3 test framework (easier community contribution), (b) typed `src/` surface, (c) forward compatibility toward L13 without a later major bump. The compat matrix row is not aspirational; it is a soft migration path.

### 2.4 Acceptance Criteria

- **AC2.1:** `composer install` succeeds on PHP 8.3, 8.4 across `prefer-lowest` and `prefer-stable` for both L12 and L13.
- **AC2.2:** Full existing PHPUnit test suite stays green on the blocking matrix (PHP 8.3, 8.4 × L12, L13 × prefer-lowest, prefer-stable = 8 combos).
- **AC2.3:** PHP 8.5 matrix combos run with `continue-on-error: true` and do not block the CI lane.
- **AC2.4:** README compat matrix and requirements reflect the new floor and upgrade rationale.
- **AC2.5:** No behavioral changes to `src/` in PR 2.

### 2.5 Out of Scope for PR 2

Pest migration, browser tests, type sweep, JS dep bumps.

---

## Section 3: PR 3 — Pest Migration on 13.x

### 3.0 Prerequisite: Pest 3 / Testbench 11 / Laravel 13 Compat Spike

Before PR 3 opens, a spike branch runs the **existing** PHPUnit suite under the target Pest 3 + Testbench 11 + Laravel 13 stack. Spike output is committed to `docs/plans/2026-04-21-totem-v13-release-design/spike-results.md` and includes:

- Exact versions resolved (`composer show pestphp/pest orchestra/testbench laravel/framework`).
- Pass/fail count per test file.
- Every new deprecation warning surfaced, **individually listed** with a disposition: `accept` (cosmetic / upstream to fix), `fix-now` (fixed in this PR), or `defer-with-ticket` (linked issue number required).
- Runtime comparison (PHPUnit vs Pest).
- Any compat issues found — each listed individually with either a workaround (and the code change required) or a "show-stopper" flag.

**Spike pass criteria (ALL must hold):**
- Zero failing tests on the target combo (Pest 3 + Testbench 11 + Laravel 13, `prefer-stable`, PHP 8.3).
- Every new deprecation has a disposition documented in the artifact. "Triaged" without disposition is not acceptance.
- No compat issue is flagged as a show-stopper.

If any criterion fails, PR 3 is blocked and the spike artifact becomes input to a revised plan. PR 3 does not open without a spike artifact that passes all three criteria.

### 3.1 Dependency Changes

**Remove from `require-dev`:**
- `phpunit/phpunit`

**Add to `require-dev`:**
- `pestphp/pest: ^3`
- `pestphp/pest-plugin-laravel: ^3`

(Exact versions pinned based on spike results in §3.0.)

Pest 3 bundles PHPUnit as a sub-dependency — keeping explicit `phpunit/phpunit` is redundant.

### 3.2 File-Level Changes

**Kept:**
- `tests/TestCase.php` — base class referenced via `uses(TestCase::class)` in `tests/Pest.php`
- `tests/TestUser.php`, `tests/TotemUserFactory.php` — fixtures, unchanged

**New:**
- `tests/Pest.php` — Pest configuration; binds `TestCase` to the feature suite.

**Deleted:**
- `phpunit.xml` / `phpunit.xml.dist` (whichever is present) — Pest generates its own config.

**Converted:**
- Every file in `tests/Feature/` + `tests/Feature/Rules/` rewritten in Pest functional syntax (`test(…)`, `it(…)`, `expect(…)`), preserving assertions one-for-one where possible.

### 3.3 Parity Verification

**Assertion count is a floor-only signal, not a primary gate.** It catches silent assertion loss (e.g., a converter deleting `$this->assertEquals(...)` lines) but does NOT catch semantic gutting (e.g., `assertTrue($x)` → `assertTrue(true)` — same count, useless test). The real parity mechanism is the per-file spot-check below.

**Procedure:**

1. Before migration, on the 13.x-baseline tip (post-PR 2): `vendor/bin/phpunit --log-junit junit-pre.xml` → parse, record total assertion count per test file.
2. After migration, on the Pest tip: `vendor/bin/pest --log-junit junit-post.xml` → parse, record total assertion count per test file.
3. **Per-file floor comparison:** the Pest assertion count for each file must **equal or exceed** the pre-migration PHPUnit count. A reduction is a blocker and must be individually justified in the PR body (e.g., "Pest `expect(...)->toBe(...)` registers a single assertion vs two in PHPUnit — semantically equivalent").
4. **Increase policy:** assertion-count *increases* are acceptable and expected. Pest's `expect()` chains (`->toBeInstanceOf()->toHaveCount()->toContain(...)`) legitimately register multiple assertions where PHPUnit used `assertContainsOnlyInstancesOf`. No PR-body justification required for increases.
5. **Spot-check policy (the real parity mechanism):**
   - Files under 50 source lines: **100% reviewed by eye** for semantic equivalence.
   - Files 50 lines or longer: **at least 25% reviewed by eye**, selected to cover the riskiest converted tests (mocks, expectations with complex setup, data providers converted to `dataset(…)` pairs).
   - Spot-check outcomes (which files reviewed, any findings) are recorded in the PR body.

Mutation testing remains out of scope for v13.

### 3.4 CI Command Update

`.github/workflows/laravel.yml`:
```yaml
- name: Execute tests
  run: vendor/bin/pest
```

### 3.5 Port v12.0.2 Popup Fix

The PR 1 Blade change (`@json()` → `Js::from()`) applies cleanly to 13.x. The PHPUnit regression test from PR 1 is rewritten in Pest form as part of file-by-file conversion, preserving the edge-case corpus and round-trip assertion from §1.3.

### 3.6 Acceptance Criteria

- **AC3.1:** `vendor/bin/pest` assertion count (from JUnit) equals or exceeds the pre-migration PHPUnit assertion count, per test file.
- **AC3.2:** All tests pass across the blocking CI matrix (PHP 8.3, 8.4 × L12, L13 × prefer-lowest, prefer-stable).
- **AC3.3:** Popup fix and regression test present on 13.x and passing.
- **AC3.4:** `phpunit/phpunit` no longer appears in `composer.json`.
- **AC3.5:** Spike artifact at `docs/plans/2026-04-21-totem-v13-release-design/spike-results.md` is present and referenced in the PR body.

---

## Section 4: PR 4 — Type Sweep on 13.x

### 4.1 Scope

`src/**/*.php` only. No migrations, no tests, no config, no vendor.

### 4.2 Targets

**Missing type hints:**
- Parameters on public/protected methods where the type is inferable from usage. Example target: `src/Events/Executed.php` currently has `public function __construct(Task $task, $started, $output)` — promote to `(Task $task, float $started, ?string $output)`.
- Return types on public/protected methods where inferable from the method body.
- `@param $var` PHPDoc entries (no type declared) — either promote to a real type in the signature or remove the uninformative PHPDoc line.

**Array shape annotations** (moderate sweep). The three repository entry points in `src/Repositories/EloquentTaskRepository.php` are the primary targets:

1. **`store(array $input)`** — enumerate actual shape (description, command, parameters, expression, is_active, timezone, notification fields, auto-cleanup fields, flags like dont_overlap/run_in_maintenance/run_on_one_server/run_in_background) and document as `@param array{description: string, command: string, ...}` PHPDoc.
2. **`update(int $id, array $input)`** — same shape as `store()` for the updatable subset; document shared and divergent keys.
3. **`import(array $input)`** — different shape again (expects `content` key holding JSON). Document as `@param array{content: string}`.

The sweep documents the **current** shapes. Where shapes genuinely diverge across the three methods, the PR surfaces the asymmetry in the PHPDoc rather than silently normalizing it. Any normalization is deliberately out-of-scope: that's a behavioral refactor, not a type sweep.

**Remove redundant PHPDoc** that duplicates the native type signature without adding information.

### 4.3 Out of Scope

- `readonly` class/property promotions.
- Converting `mixed` parameters where the API is intentionally polymorphic.
- PHPStan or Psalm baseline introduction (deferred post-v13.0).
- Normalization of divergent array shapes across repository methods (behavioral refactor).

**Scope-creep guard (enforced at PR review):** PR 4's diff MUST be limited to type hints, PHPDoc annotations, and removal of redundant PHPDoc. Any change that alters runtime behavior — method renames, class renames, method extraction, deletion of dead code, condition refactors — is extracted to a separate PR. The `/review` checklist must include this guard and reject a PR 4 diff containing behavior changes.

### 4.4 Value Proposition

**This sweep provides documentation value, not static-analysis enforcement.** With PHPStan out of scope, incorrect types drift silently. The PR improves IDE autocomplete, code review clarity, and readiness for a later PHPStan introduction — it does not, by itself, guarantee correctness.

### 4.5 Verification

- Full Pest suite green after each file batch (sweep runs file-by-file).
- `./vendor/bin/pint` run at end of PR for code style.

### 4.6 Acceptance Criteria

- **AC4.1:** No public/protected method in `src/` has an untyped parameter where the type is inferable from usage and not intentionally polymorphic.
- **AC4.2:** No public/protected method in `src/` has a missing return type where the return type is inferable from the method body.
- **AC4.3:** The three repository entry points (`store`, `update`, `import`) have array-shape `@param` PHPDoc reflecting their current input contracts; asymmetries surfaced in PHPDoc comments.
- **AC4.4:** Full Pest suite stays green on the blocking CI matrix after the sweep.
- **AC4.5:** `vendor/bin/pint --test` reports zero style violations.
- **AC4.6:** PR 4 diff is limited to type hints and PHPDoc. `git diff --stat` shows only added/modified lines that are type declarations, PHPDoc blocks, or whitespace; any behavioral change (method rename, extraction, dead-code removal, conditional refactor) is extracted to a separate PR.

---

## Section 5: PR 5 — Release Prep (v13.0.0)

### 5.1 README Final Pass

- Compatibility matrix reflects final supported versions (see §2.3).
- Requirements section: PHP 8.3+, Laravel 12.x or 13.x.
- Build badge branch ref switched to `13.x`.
- Test instructions mention `vendor/bin/pest` as the canonical command.
- Browser tests deferral line: "Browser-level regression coverage is scheduled for v13.1; see [TICKET-###]."

### 5.2 CHANGELOG

**Format:** Keep a Changelog 1.1.0 (`https://keepachangelog.com/en/1.1.0/`). All entries use bracketed-version headings with ISO dates and `### Added / ### Changed / ### Removed / ### Fixed / ### Deprecated` subsections.

Backfill `## [12.0.2] - YYYY-MM-DD` entry (popup fix — see §1.4 for detail requirements).

Add `## [13.0.0] - YYYY-MM-DD` entry with these subsections:

```
## [13.0.0] - YYYY-MM-DD

### Added
- Laravel 13 support, verified against `laravel/framework` v13.0.0+ (see upgrade-guide citations in PR 2 body).
- PHP 8.5 in CI matrix as a non-blocking experimental lane with documented promotion criteria.
- `prefer-lowest` CI lane to verify declared composer floors actually resolve.

### Changed
- Minimum PHP version is now 8.3 (was 8.2).
- Test suite migrated from PHPUnit to Pest 3.
- Parameter/return types and array-shape PHPDoc annotations added across `src/`.

### Removed
- Laravel 10 and 11 support.
- PHP 8.2 support.
- `phpunit/phpunit` from `require-dev` (bundled by Pest).

### Fixed
- Empty output popup on task-view page (forward-ported from v12.0.2).

### Deferred
- Browser test infrastructure deferred to v13.1; see [TICKET-###] for the tracking issue and target date.
```

### 5.3 commonmark CVE Exposure Statement (source-of-truth release-notes text)

The following paragraph ships **verbatim** in the v13.0.0 release notes (and is quoted on any GitHub advisory response). This is the canonical text; §7 contains the supporting analysis and refers back to this paragraph.

> **Note on `composer audit` findings:** Installing Totem v13.0.0 surfaces two medium-severity advisories in `league/commonmark` (CVE-2026-33347 and CVE-2026-30838, both in `league/commonmark` ≤ 2.8.1). `league/commonmark` is a transitive dependency of `laravel/framework`; Totem does not require it directly. The advisories affect the `embed` and `DisallowedRawHtml` commonmark extensions, which are **opt-in** and **not enabled** in Laravel's default mail rendering. Totem's notification code (`src/Notifications/TaskCompleted.php`) uses `MailMessage->line()`, which passes through Laravel's default markdown pipeline without enabling either affected extension. The CVE code paths are therefore unreachable from Totem's runtime. Downstream users who need to clear the `composer audit` output can suppress these specific advisories via `composer audit --ignore` or wait for the transitive bump in a future `laravel/framework` release.

### 5.4 Release

Tag `v13.0.0` after merge to `13.x` and CI green on blocking matrix.

### 5.5 Acceptance Criteria

- **AC5.1:** README compat matrix and requirements reflect final scope and deferred items.
- **AC5.2:** CHANGELOG has both `v12.0.2` and `v13.0.0` entries, in Keep-a-Changelog format (see §1.4 / §5.2).
- **AC5.3:** Release notes include the commonmark CVE exposure statement (source: §5.3; cross-referenced by §7).
- **AC5.4:** Follow-up ticket for browser tests (PR F) exists with named owner and target date before tag.
- **AC5.5:** CI green on blocking matrix.
- **AC5.6:** Tag `v13.0.0` created on `13.x` HEAD.
- **AC5.7 (gate):** §9 Status section's "Follow-up ticket for PR F" checkbox has a live, resolving URL filled in BEFORE `git tag v13.0.0` is executed. The URL is pasted into the CHANGELOG `v13.0.0` entry as the `[TICKET-###]` reference. This AC is a hard gate — if the URL is missing or 404s, the tag operation does not proceed.

---

## Section 6: PR F (DEFERRED to v13.1) — Browser Testing Infrastructure

### 6.1 Why Deferred

At design time (2026-04-21), the Pest browser testing package and its pairing with Playwright for package-context (testbench-served) applications is not stable enough to pin with confidence. Shipping it alongside the other v13 changes would either (a) block v13.0 on unstable external infrastructure, or (b) force a "validate during implementation" clause that undermines the design doc's purpose.

The decision: defer to v13.1. v13.0's popup fix is still covered by PR 3's server-side round-trip test (§1.3 / §3.5), which catches the specific regression class at the Blade→attribute boundary.

### 6.2 v13.1 Work Sketch

Deliberately low-detail because this is a deferred scope. Full design doc will accompany the v13.1 follow-up ticket.

**Settled:**
- `@playwright/test` added to `package.json` devDependencies; `npx playwright install --with-deps chromium` in CI.
- `tests/Browser/` with three tests: popup regression (load-bearing), execute button flow, dashboard smoke test.
- Browser CI lane pinned to **PHP 8.3 × Laravel 12.x × prefer-stable** (the stable shipping target).

**Open questions (to be resolved during v13.1 planning — owner TBD in follow-up ticket):**
- **Browser plugin choice:** Pest 3's ecosystem for browser testing (plugin name, stable release, package-context support against testbench-served apps) requires re-evaluation at v13.1 planning time. Default assumption is a Pest browser plugin, but if the ecosystem has evolved to favor a different approach (e.g., a Playwright-native harness invoked from Pest), that's in scope to consider.
- **HTTP server lifecycle:** the approach below is the **fallback** if the chosen browser plugin does NOT provide its own server management. If the plugin bundles a server lifecycle layer, the plugin's approach takes precedence and this section is superseded.

**Fallback server lifecycle** (used only if the plugin lacks its own):
- Dynamic port allocation (bind to `0`, kernel-assigned).
- URL handoff to Playwright via env var.
- Teardown via `register_shutdown_function` + SIGTERM handler.
- Orphan cleanup via PID file + stale-process check at suite start.
- Parallel-safe: per-Pest-worker port allocation.

**Resolution gate:** Both open questions must be resolved and documented in the v13.1 design doc before any implementation PR under PR F opens.

### 6.3 Follow-Up Ticket Requirements

**Blocking v13.0.0 release:** A tracked issue (Jira or GitHub) MUST exist before v13.0.0 is tagged, containing:
- Named owner.
- Target date for v13.1 design doc completion.
- Link from v13.0.0 release notes.
- Link from this design doc's Status section (see §9).

---

## Section 7: commonmark CVE Exposure Analysis

**Release-notes text:** see §5.3 for the canonical paragraph shipped in v13.0.0 release notes. This section provides the supporting analysis that justifies that paragraph.

### 7.1 The Advisories

`composer audit` surfaces two medium CVEs in `league/commonmark` as of 2026-04-21:

- **CVE-2026-33347** — embed extension allowed_domains bypass. Affects `>=2.3.0, <=2.8.1`.
- **CVE-2026-30838** — DisallowedRawHtml extension bypass via whitespace in HTML tag names. Affects `>=2.0.0, <=2.8.0`.

### 7.2 Reachability Analysis

`laravel/framework` (v12.53.0 at design time) requires `league/commonmark ^2.7` transitively. `src/Notifications/TaskCompleted.php::toMail()` calls `MailMessage->line()`, which feeds into Laravel's markdown mail pipeline. That pipeline renders markdown → HTML via `Illuminate\Mail\Markdown`, which in turn constructs a commonmark `CommonMarkConverter` (or `Environment`) configured by Laravel's defaults.

### 7.3 Why the Affected Extensions Are Unreachable

**Citation (verify at PR 2 time):**
- Framework source file: `vendor/laravel/framework/src/Illuminate/Mail/Markdown.php` (Laravel 13.x branch: `https://github.com/laravel/framework/blob/13.x/src/Illuminate/Mail/Markdown.php`).
- Default environment setup: Laravel's markdown environment uses `League\CommonMark\CommonMarkConverter` (or the Environment + GFM extensions only). Neither the `EmbedExtension` nor the `DisallowedRawHtmlExtension` is added by Laravel's default configuration.
- Totem's code: `src/Notifications/TaskCompleted.php` does not call `addExtension(...)` on any commonmark-related object; it relies entirely on Laravel's defaults.

**Conclusion:** The two CVE code paths require the affected extensions to be registered on the commonmark Environment. Laravel's default mail markdown environment does not register them, and Totem does not customize the environment. Therefore the CVE code paths are **unreachable** from Totem's runtime.

### 7.4 Verification Before v13.0.0 Tag

The citations in §7.3 must be re-verified at PR 5 time against the **currently resolved** Laravel 13.x minor. If Laravel 13.x at that time has altered its default markdown environment to include either extension, this analysis is invalidated and either (a) the transitive must be bumped, or (b) the exposure statement must be rewritten. Verification is tracked in §9.

### 7.5 Re-evaluation Triggers

This analysis must be revisited if any of the following occur later in Totem's lifecycle:
- Totem adds a feature that configures commonmark extensions directly (e.g., rendering user-supplied markdown).
- Totem switches from `MailMessage->line()` to a different rendering path (e.g., custom blade mail templates that invoke commonmark).
- Laravel changes its default markdown environment to enable either affected extension.

---

## Section 8: AC → Test → CI Lane Mapping

Per project rules `.claude/rules/40-testing-quality.md` (AC mapping) and `.claude/rules/25-spec-driven-development.md` (SDD traceability).

### PR 1 (v12.0.2)

| AC    | Test                                                                          | CI Lane                       |
|-------|-------------------------------------------------------------------------------|-------------------------------|
| AC1.1 | `tests/Feature/ViewTaskTest.php::test_output_attribute_roundtrips_edge_cases` | 12.x matrix (existing shape)  |
| AC1.2 | Same test w/ negative control PHPDoc                                          | 12.x matrix (existing shape)  |
| AC1.3 | Full existing 12.x suite                                                      | 12.x matrix                   |
| AC1.4 | `CHANGELOG.md` diff — manually reviewed                                       | N/A (docs)                    |

### PR 2 (v13.0 baseline)

| AC    | Test                                                                       | CI Lane                                                       |
|-------|----------------------------------------------------------------------------|---------------------------------------------------------------|
| AC2.1 | `composer install` step                                                    | All 13.x blocking combos                                      |
| AC2.2 | Full existing PHPUnit suite                                                | PHP 8.3, 8.4 × L12, L13 × prefer-lowest, prefer-stable        |
| AC2.3 | CI job annotations (PHP 8.5 lane `continue-on-error`)                      | PHP 8.5 combos (non-blocking)                                 |
| AC2.4 | README diff — manually reviewed                                            | N/A (docs)                                                    |
| AC2.5 | `git diff src/` — manually reviewed; empty for PR 2                        | N/A (review gate)                                             |

### PR 3 (Pest migration)

| AC    | Test                                                                       | CI Lane                                                       |
|-------|----------------------------------------------------------------------------|---------------------------------------------------------------|
| AC3.1 | Assertion count comparison (JUnit pre/post) — CI step added for PR 3       | Blocking matrix                                               |
| AC3.2 | Full Pest suite                                                            | PHP 8.3, 8.4 × L12, L13 × prefer-lowest, prefer-stable        |
| AC3.3 | `tests/Feature/ViewTaskTest.php` (Pest-converted)                          | Blocking matrix                                               |
| AC3.4 | `composer.json` diff — manually reviewed                                   | N/A (docs)                                                    |
| AC3.5 | Spike artifact presence + PR body reference — manually reviewed            | N/A (docs)                                                    |

### PR 4 (Type sweep)

| AC    | Test                                                                       | CI Lane                                                       |
|-------|----------------------------------------------------------------------------|---------------------------------------------------------------|
| AC4.1 | Manual review of `src/**/*.php` diff for untyped params                    | N/A (review gate)                                             |
| AC4.2 | Manual review of `src/**/*.php` diff for missing return types              | N/A (review gate)                                             |
| AC4.3 | Manual review of `src/Repositories/EloquentTaskRepository.php` PHPDoc      | N/A (review gate)                                             |
| AC4.4 | Full Pest suite                                                            | Blocking matrix                                               |
| AC4.5 | `vendor/bin/pint --test`                                                   | Blocking matrix (new CI step)                                 |
| AC4.6 | `git diff --stat` analysis — reviewer asserts types/PHPDoc-only diff        | N/A (review gate)                                             |

### PR 5 (Release prep)

| AC    | Test                                                                       | CI Lane                                                       |
|-------|----------------------------------------------------------------------------|---------------------------------------------------------------|
| AC5.1 | README diff — manually reviewed                                            | N/A (docs)                                                    |
| AC5.2 | CHANGELOG diff — manually reviewed against Keep-a-Changelog 1.1.0 format   | N/A (docs)                                                    |
| AC5.3 | Release notes contain §5.3 paragraph verbatim — manually verified          | N/A (docs)                                                    |
| AC5.4 | Follow-up ticket URL present in release notes — manually verified          | N/A (docs)                                                    |
| AC5.5 | Full Pest suite                                                            | Blocking matrix                                               |
| AC5.6 | `git tag` listing                                                          | N/A (release gate)                                            |
| AC5.7 | §9 checkbox "Follow-up ticket for PR F" has live URL before tag — manually verified and URL pasted into v13.0.0 CHANGELOG entry | N/A (release gate) |

---

## Risks & Mitigations

- **Pest 3 + Laravel 13 + Testbench 11 stack friction** — Gated by §3.0 compat spike. PR 3 does not open without spike artifact. If show-stopper, plan revises.
- **Type sweep surfaces hidden bugs** — PR 4 runs full Pest suite after each file batch; any real bug is fixed in-PR or split into a separate hotfix.
- **Users stranded on L10/L11** — v11.x and v12.x maintenance branches remain indefinitely; no force-upgrade.
- **commonmark CVEs panic downstream users** — Exposure statement in release notes (§5.3 / §7) documents the analysis.
- **PHP 8.5 upstream instability** — Non-blocking via `continue-on-error`. Promotion criteria documented in §2.2. Ignored-red-X rot mitigated by explicit promotion gates.
- **Browser testing deferral slips indefinitely** — Follow-up ticket with owner + date is a blocking precondition for v13.0.0 tag (AC5.4).

## Rollback

Revert order MUST be the reverse of land order. Explicitly:
- Revert PR 5 (release prep) — trivial; docs only.
- Revert PR 4 (type sweep) — requires full Pest suite to re-pass post-revert.
- Revert PR 3 (Pest migration) — requires restoring `phpunit/phpunit` in `composer.json` and reverting `tests/Feature/**` conversions. PR 4's verification via Pest is invalidated.
- Revert PR 2 (baseline) — restores prior composer constraints and CI matrix.
- PR 1 on 12.x is independent of the 13.x chain; reverting it yields v12.0.3 with the fix removed.

If v13.0.0 has a post-release issue, an emergency v13.0.1 is easier than reverting; all changes are mechanical.

## Out of Scope for v13.0

- Stashed `schedules view` / `ScheduleRow` WIP — stays stashed for a later release.
- Browser test infrastructure — deferred to v13.1 (see §6).
- `readonly` class/property sweep.
- PHPStan or Psalm baseline.
- Pest parallel execution tuning.
- Cross-browser test coverage (Firefox/WebKit) — deferred along with browser tests.
- Full JS dep overhaul. `package.json` untouched in v13.0.
- Normalization of divergent repository input array shapes.

## Section 9: Status

- [ ] Compat spike (§3.0) — artifact path: `docs/plans/2026-04-21-totem-v13-release-design/spike-results.md`; pass criteria in §3.0.
- [ ] Follow-up ticket for PR F (browser tests, v13.1) — URL pending. **Required live before `git tag v13.0.0` (AC5.7).**
- [ ] Laravel 13 upgrade-guide anchor citations (§2.1) — re-verified at PR 2 time
- [ ] §7.3 commonmark citation — re-verified at PR 5 time against resolved Laravel 13.x minor (§7.4)
- [ ] ACs re-validated against this revision
- [x] Risk classification assigned: **MEDIUM**
- [x] AC→test mapping present (§8)
- [x] commonmark CVE exposure analysis present (§5.3 source + §7 supporting)
- [x] Rollback order documented
- [x] CI matrix YAML is compilable (§2.2)
- [x] Spike exit criteria defined (§3.0)
- [x] Assertion-parity policy defined as floor + spot-check (§3.3)
- [x] CHANGELOG format pinned to Keep a Changelog 1.1.0 (§1.4 / §5.2)
- [x] PR 4 scope guard AC (§4.6)
- [x] PR F deferral blocking AC (§5.7)
