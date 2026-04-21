# Totem v13.0 Baseline (PR 2) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bump the Totem package's composer floor (PHP 8.3+, Laravel 12.x and 13.x; drop PHP 8.2 and Laravel 10/11), rewrite the CI matrix to match, and update README compatibility documentation. No code changes to `src/`. No test-framework swap.

**Architecture:** Mechanical version-bump PR. `composer.json` gets new `require` / `require-dev` constraints. `.github/workflows/laravel.yml` gets a new `matrix:` block with PHP 8.3/8.4/8.5 × Laravel 12/13 × prefer-lowest/prefer-stable, with 8.5 as a non-blocking experimental lane. `README.md` compat matrix expanded. The existing PHPUnit test suite runs unchanged; Pest migration lands in PR 3.

**Tech Stack:** PHP 8.3+ (floor), Laravel 12.0+ / 13.0+, PHPUnit 11.0+ / 12.0+, Orchestra Testbench 10.0+ / 11.0+. GitHub Actions matrix build. Branch: `feat/v13-baseline` off `13.x`.

**Spec:** `docs/plans/2026-04-21-totem-v13-release-design.md` §2 (Section 2: PR 2). ACs AC2.1–AC2.5.

---

## File Structure

| File | Responsibility | Action |
|------|----------------|--------|
| `composer.json` | Package manifest: runtime + dev requirements, autoload, metadata. | Modify (require + require-dev blocks only) |
| `.github/workflows/laravel.yml` | CI workflow. Matrix build that runs PHPUnit against each PHP × Laravel × dependency-strategy combo. | Replace matrix block + job structure |
| `README.md` | User-facing package documentation. Lines 5 (build badge), 15–25 (compat matrix + requirements). | Modify three specific blocks |
| `composer.lock` | Composer resolution lockfile. | Regenerated as a side effect of `composer update`; commit the regenerated file. |

No changes to `src/`, `tests/`, `config/`, `database/`, `routes/`, `resources/`.

---

## Task 1: Branch setup and sanity checks

Goal: start from a clean 13.x tip.

- [ ] **Step 1: Verify current branch**

```bash
git rev-parse --abbrev-ref HEAD
git status --short
```

Expected: on `13.x` with a clean working tree (untracked `docs/plans/*.md` files are acceptable).

If not on `13.x`:
```bash
git checkout 13.x
git pull --ff-only origin 13.x
```

- [ ] **Step 2: Create the PR branch**

```bash
git checkout -b feat/v13-baseline
```

- [ ] **Step 3: Confirm baseline composer.json state**

```bash
grep -A1 '"php":' composer.json | head -2
grep '"illuminate/bus":' composer.json
grep '"orchestra/testbench":' composer.json
grep '"phpunit/phpunit":' composer.json
```

Expected (pre-bump state):
```
"php": "^8.2.0",
"illuminate/bus": "^10.0|^11.0|^12.0",
"orchestra/testbench": "^8.0|^9.0|^10.0",
"phpunit/phpunit": "^10.0|^11.0|^12.0"
```

If any of these differ from the pre-bump values above, STOP — reconcile with the spec before continuing.

- [ ] **Step 4: Verify DB_HOST is local**

Per `.claude/rules/00-project-critical.md` §6:

```bash
grep '^DB_HOST' .env 2>/dev/null || echo "no .env DB_HOST — ok, testbench uses sqlite :memory:"
```

Expected: no `.env` or `DB_HOST=127.0.0.1`/`localhost`. STOP if non-local.

---

## Task 2: Update composer.json require block

Goal: tighten the PHP floor to 8.3 and drop Laravel 10/11 from every `illuminate/*` constraint.

**Files:**
- Modify: `composer.json` lines 22–31 (the `"require":` object)

- [ ] **Step 1: Edit the require block**

Before:
```json
"require": {
    "php": "^8.2.0",
    "ext-json": "*",
    "illuminate/bus": "^10.0|^11.0|^12.0",
    "illuminate/console": "^10.0|^11.0|^12.0",
    "illuminate/contracts": "^10.0|^11.0|^12.0",
    "illuminate/database": "^10.0|^11.0|^12.0",
    "illuminate/events": "^10.0|^11.0|^12.0",
    "illuminate/notifications": "^10.0|^11.0|^12.0"
},
```

After:
```json
"require": {
    "php": "^8.3",
    "ext-json": "*",
    "illuminate/bus": "^12.0|^13.0",
    "illuminate/console": "^12.0|^13.0",
    "illuminate/contracts": "^12.0|^13.0",
    "illuminate/database": "^12.0|^13.0",
    "illuminate/events": "^12.0|^13.0",
    "illuminate/notifications": "^12.0|^13.0"
},
```

Note the `php` constraint drops the redundant `.0` patch (`^8.2.0` → `^8.3`). Both forms behave identically for composer, but the shorter form matches the style used elsewhere in the Laravel ecosystem.

- [ ] **Step 2: Verify the edit**

```bash
grep -A1 '"php":' composer.json | head -2
grep '"illuminate/bus":' composer.json
```

Expected:
```
"php": "^8.3",
"illuminate/bus": "^12.0|^13.0",
```

---

## Task 3: Update composer.json require-dev block

Goal: bump Testbench and PHPUnit floors. Keep PHPUnit present (PR 3 removes it).

**Files:**
- Modify: `composer.json` lines 32–37 (the `"require-dev":` object)

- [ ] **Step 1: Edit the require-dev block**

Before:
```json
"require-dev": {
    "laravel/slack-notification-channel": "^3.7",
    "laravel/vonage-notification-channel": "^3.3",
    "orchestra/testbench": "^8.0|^9.0|^10.0",
    "phpunit/phpunit": "^10.0|^11.0|^12.0"
},
```

After:
```json
"require-dev": {
    "laravel/slack-notification-channel": "^3.7",
    "laravel/vonage-notification-channel": "^3.3",
    "orchestra/testbench": "^10.0|^11.0",
    "phpunit/phpunit": "^11.0|^12.0"
},
```

Rationale:
- `orchestra/testbench ^10.0` matches Laravel 12.x. `^11.0` matches Laravel 13.x. Dropping `^8.0|^9.0` mirrors dropping Laravel 10/11.
- `phpunit ^11.0|^12.0` matches the PHP 8.3+ minimum. PHPUnit 10 required PHP 8.1 — dropping it aligns with the PHP 8.3 floor.

- [ ] **Step 2: Verify the edit**

```bash
grep '"orchestra/testbench":' composer.json
grep '"phpunit/phpunit":' composer.json
```

Expected:
```
"orchestra/testbench": "^10.0|^11.0",
"phpunit/phpunit": "^11.0|^12.0"
```

---

## Task 4: Regenerate composer.lock and run tests locally

Goal: prove the new constraints resolve and the existing test suite stays green under the new floors.

- [ ] **Step 1: Validate composer.json syntax**

```bash
composer validate --strict
```

Expected: `./composer.json is valid`. If warnings or errors appear, fix them before continuing.

- [ ] **Step 2: Regenerate the lockfile**

```bash
composer update --prefer-dist --no-interaction
```

Expected: composer resolves Laravel 13.x (latest stable), Testbench 11.x, PHPUnit 12.x (or their latest stable within the constraint range). No "cannot be satisfied" errors.

If resolution fails:
- Read the error output carefully — it names the conflict.
- Common cause: a transitive dep requires an `illuminate/*` version outside our constraint. Either raise the constraint (with a spec update) or pin the transitive.
- If resolution fails only on `prefer-lowest`, that's expected — we handle that in CI separately (Task 6).

- [ ] **Step 3: Confirm resolved versions**

```bash
composer show illuminate/support | head -3
composer show orchestra/testbench | head -3
composer show phpunit/phpunit | head -3
```

Record the resolved versions in a scratch note. They will appear in the PR body's "resolved versions" section.

- [ ] **Step 4: Run the test suite**

```bash
APP_ENV=testing vendor/bin/phpunit
```

Expected: all tests pass. This proves the existing code compiles against Laravel 13 and PHP 8.3+.

If tests fail:
- Read the failure — is it a deprecation warning (soft) or a behavioral change (hard)?
- Deprecations are tolerable on the `prefer-stable` path; they must be surfaced to the spec for disposition.
- Behavioral failures block the PR. STOP and document the failure mode. May require raising the `illuminate/*` floor (e.g., `^12.3` if a feature landed in 12.3).

- [ ] **Step 5: Commit the composer changes**

```bash
git add composer.json composer.lock
git commit -m "chore: bump composer floors to PHP 8.3, Laravel 12|13, Testbench 10|11, PHPUnit 11|12"
```

---

## Task 5: Rewrite the CI workflow matrix

Goal: replace the old `PHP 8.2/8.3/8.4 × Laravel 11/12` matrix with the new `PHP 8.3/8.4/8.5 × Laravel 12/13 × prefer-lowest/prefer-stable` shape, with 8.5 as a non-blocking experimental lane.

**Files:**
- Modify (full rewrite): `.github/workflows/laravel.yml`

- [ ] **Step 1: Read the current workflow**

```bash
cat .github/workflows/laravel.yml
```

Expected (current state — 37 lines):
```yaml
name: Laravel

on: [push, pull_request]

jobs:
  laravel-tests:
    runs-on: ${{ matrix.os }}
    strategy:
      fail-fast: true
      matrix:
        os: [ ubuntu-latest ]
        php: [ 8.2, 8.3, 8.4 ]
        laravel: [ 11.*, 12.* ]
        stability: [ prefer-stable ]
    name: P${{ matrix.php }} - L${{ matrix.laravel }} - ${{ matrix.stability }} - ${{ matrix.os }}

    steps:
    - uses: actions/checkout@v4
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: ${{ matrix.php }}
        extensions: dom, curl, libxml, mbstring, zip, pcntl, pdo, sqlite, pdo_sqlite, bcmath, soap, intl, gd, exif, iconv, imagick, fileinfo
        coverage: none
    - name: Cache dependencies
      uses: actions/cache@v4
      with:
        path: ~/.composer/cache/files
        key: ${{ runner.os }}-${{ matrix.php }}-composer-${{ hashFiles('**/composer.lock') }}
        restore-keys: |
          ${{ runner.os }}-${{ matrix.php }}-composer-
    - name: Install dependencies
      run: |
        composer require "laravel/framework:${{ matrix.laravel }}" --no-interaction --no-update
        composer update --${{ matrix.stability }} --prefer-dist --no-interaction
    - name: Execute tests (Unit and Feature tests) via PHPUnit
      run: vendor/bin/phpunit
```

- [ ] **Step 2: Replace the entire workflow file**

Write the new file. Complete content:

```yaml
name: Laravel

on: [push, pull_request]

jobs:
  laravel-tests:
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
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: dom, curl, libxml, mbstring, zip, pcntl, pdo, sqlite, pdo_sqlite, bcmath, soap, intl, gd, exif, iconv, imagick, fileinfo
          coverage: none

      - name: Cache composer dependencies
        uses: actions/cache@v4
        with:
          path: ~/.composer/cache/files
          key: ${{ runner.os }}-php${{ matrix.php }}-L${{ matrix.laravel }}-${{ matrix.dependencies }}-${{ hashFiles('**/composer.json') }}
          restore-keys: |
            ${{ runner.os }}-php${{ matrix.php }}-L${{ matrix.laravel }}-${{ matrix.dependencies }}-
            ${{ runner.os }}-php${{ matrix.php }}-

      - name: Pin Laravel version for this job
        run: |
          composer require "laravel/framework:${{ matrix.laravel }}" --no-interaction --no-update

      - name: Install dependencies (${{ matrix.dependencies }})
        run: |
          composer update --${{ matrix.dependencies }} --prefer-dist --no-interaction

      - name: Verify illuminate floor (prefer-lowest only)
        if: matrix.dependencies == 'prefer-lowest' && matrix.experimental != true
        run: |
          RESOLVED=$(composer show illuminate/support | awk '/^versions/ {print $4}' | head -1 | sed 's/[*,]//g')
          RESOLVED_CLEAN=$(echo "$RESOLVED" | sed 's/^v//')
          LARAVEL_MAJOR="${{ matrix.laravel }}"
          case "$LARAVEL_MAJOR" in
            12.*) MIN="12.0.0" ;;
            13.*) MIN="13.0.0" ;;
            *) echo "Unknown Laravel major $LARAVEL_MAJOR"; exit 1 ;;
          esac
          php -r "
            \$resolved = '${RESOLVED_CLEAN}';
            \$min      = '${MIN}';
            if (version_compare(\$resolved, \$min, '<')) {
              fwrite(STDERR, \"illuminate/support resolved to \$resolved, below declared floor \$min for Laravel ${LARAVEL_MAJOR}\\n\");
              exit(1);
            }
            echo \"illuminate/support resolved to \$resolved (floor \$min) — ok\\n\";
          "

      - name: Execute tests (PHPUnit)
        # PR 2 uses PHPUnit; PR 3 replaces this with `vendor/bin/pest`.
        run: vendor/bin/phpunit
```

Key differences vs. the pre-PR workflow:

- `continue-on-error: ${{ matrix.experimental == true }}` gates non-blocking 8.5 lanes.
- `fail-fast: false` — one failing lane does not cancel the others. Important for `prefer-lowest` diagnosis.
- `matrix.php` now `['8.3', '8.4']` as the blocking set; 8.5 is moved into `include` with `experimental: true`.
- `matrix.laravel` now `['12.*', '13.*']` — dropped 11.
- `matrix.dependencies` is new (replaces `stability`). Two values: `prefer-lowest`, `prefer-stable`.
- New "Verify illuminate floor" step that fails the lane if `prefer-lowest` resolves below the declared composer floor.
- Cache key includes `matrix.dependencies` so prefer-lowest and prefer-stable don't clobber each other.

- [ ] **Step 3: Validate YAML**

```bash
command -v yamllint >/dev/null && yamllint .github/workflows/laravel.yml || echo "yamllint not installed — skipping"
```

If yamllint is installed, expect no errors. If not installed, GitHub Actions will validate on push.

- [ ] **Step 4: Commit the workflow**

```bash
git add .github/workflows/laravel.yml
git commit -m "ci: rewrite matrix for v13 (PHP 8.3-8.5, Laravel 12/13, prefer-lowest+stable)"
```

---

## Task 6: Update README compatibility matrix and requirements

Goal: replace the two-row compat table with the v13 six-row version; bump requirements; switch build badge to `13.x`.

**Files:**
- Modify: `README.md` lines 5 (badge), 15–25 (compat + requirements)

- [ ] **Step 1: Read the current README header**

```bash
head -30 README.md
```

Expected (current):

```markdown
<p align="center">
  <img src="https://github.com/codestudiohq/laravel-totem/blob/8.0/resources/assets/img/totem.png?raw=true" alt="Laravel Totem"/>
</p>
<p align="center">
<img src="https://github.com/always-open/laravel-totem/workflows/Laravel/badge.svg?branch=11.x" alt="Build Status">
<a href="https://packagist.org/packages/studio/laravel-totem"><img src="https://poser.pugx.org/studio/laravel-totem/license.svg" alt="License"></a>
</p>

# Introduction
...
## Documentation

#### Compatibility Matrix

| <span align="left">Laravel</span> | <span align="left">Totem</span> |
|:----------------------------------|--------------------------------:|
| 12.x                              |                            11.x |
| 11.x                              |                            11.x |

#### Requirements

- PHP 8.2+
- Laravel 11.x or 12.x
```

- [ ] **Step 2: Update the build badge branch reference**

Replace the line:
```html
<img src="https://github.com/always-open/laravel-totem/workflows/Laravel/badge.svg?branch=11.x" alt="Build Status">
```

With:
```html
<img src="https://github.com/always-open/laravel-totem/workflows/Laravel/badge.svg?branch=13.x" alt="Build Status">
```

- [ ] **Step 3: Replace the Compatibility Matrix section**

Find the section:
```markdown
#### Compatibility Matrix

| <span align="left">Laravel</span> | <span align="left">Totem</span> |
|:----------------------------------|--------------------------------:|
| 12.x                              |                            11.x |
| 11.x                              |                            11.x |
```

Replace with:
```markdown
#### Compatibility Matrix

| Laravel | Totem | Notes                                        |
|:--------|------:|:---------------------------------------------|
| 13.x    | 13.x  | Current                                      |
| 12.x    | 13.x  | Recommended — Pest test framework, improved types |
| 12.x    | 12.x  | Maintained                                   |
| 11.x    | 12.x  | Maintained                                   |
| 11.x    | 11.x  | Maintained                                   |
| 10.x    | 11.x  | Maintained                                   |
```

- [ ] **Step 4: Replace the Requirements section**

Find:
```markdown
#### Requirements

- PHP 8.2+
- Laravel 11.x or 12.x
```

Replace with:
```markdown
#### Requirements

- PHP 8.3+
- Laravel 12.x or 13.x

Earlier Totem majors are maintained for legacy Laravel support:
- Totem 12.x supports Laravel 11.x and 12.x (PHP 8.2+).
- Totem 11.x supports Laravel 10.x, 11.x, and 12.x (PHP 8.2+).
```

- [ ] **Step 5: Verify the edits**

```bash
grep 'badge.svg?branch=' README.md
grep -A8 '#### Compatibility Matrix' README.md
grep -A6 '#### Requirements' README.md
```

Expected: badge URL uses `branch=13.x`; compat table has 6 rows headed by `13.x | 13.x`; requirements state `PHP 8.3+` and `Laravel 12.x or 13.x`.

- [ ] **Step 6: Commit the README**

```bash
git add README.md
git commit -m "docs: update README compat matrix and requirements for v13"
```

---

## Task 7: Run the full matrix locally (optional but strongly recommended)

Goal: catch matrix-specific failures before CI.

This task is OPTIONAL — if your local environment can't easily switch PHP versions (8.3 / 8.4), skip and rely on CI to catch issues. If you have PHP version managers (phpbrew, asdf, herd, etc.), run this task for faster feedback.

- [ ] **Step 1: Run against Laravel 13 stable, PHP 8.3**

```bash
composer require "laravel/framework:13.*" --no-interaction --no-update
composer update --prefer-stable --prefer-dist --no-interaction
APP_ENV=testing vendor/bin/phpunit
```

Expected: all tests pass.

- [ ] **Step 2: Run against Laravel 12 prefer-lowest, PHP 8.3**

```bash
composer require "laravel/framework:12.*" --no-interaction --no-update
composer update --prefer-lowest --prefer-dist --no-interaction
APP_ENV=testing vendor/bin/phpunit
```

Expected: all tests pass. If `prefer-lowest` resolves to a Laravel 12.x that lacks an API Totem uses, the suite fails here — that's the signal to raise the floor (e.g., `^12.3`).

- [ ] **Step 3: Restore the default composer state**

```bash
# Restore composer.json to the committed state (no explicit laravel/framework entry)
git checkout composer.json composer.lock
# OR, reapply the PR 2 changes and re-resolve
composer update --prefer-stable --prefer-dist --no-interaction
```

Do NOT commit any transient changes from this task's `composer require` invocations.

---

## Task 8: Push the branch and open the PR

- [ ] **Step 1: Push**

```bash
git push -u origin feat/v13-baseline
```

- [ ] **Step 2: Open the PR against `13.x`**

Title: `v13 baseline — drop L10/11 + PHP 8.2, add Laravel 13, bump CI matrix`

Body:

```markdown
## Summary

Mechanical version-bump PR for the v13.0 release line. No `src/` changes. No test-framework changes. Everything else follows:

- PHP floor: 8.3 (dropped 8.2).
- Laravel: 12.x and 13.x (dropped 10.x, 11.x).
- Testbench: `^10.0|^11.0` (dropped 8, 9).
- PHPUnit: `^11.0|^12.0` (dropped 10). Still present — PR 3 swaps to Pest.
- CI matrix: PHP {8.3, 8.4} × Laravel {12, 13} × dependencies {prefer-lowest, prefer-stable} = 8 blocking combos. PHP 8.5 × same = 4 non-blocking experimental combos.
- README: compat matrix expanded to reflect all supported Totem majors.

## Laravel 13 upgrade-guide citations

The following upgrade-guide anchors were walked to verify Totem has no L13 breaking changes to accommodate:

- Service-provider auto-discovery (`extra.laravel.providers` unchanged): `https://laravel.com/docs/13.x/upgrade#package-discovery`
- `Illuminate\Support\Js` (used in v12.0.2 popup fix forward-ported in PR 3): `https://laravel.com/docs/13.x/upgrade#strings` cross-ref `https://github.com/laravel/framework/blob/13.x/src/Illuminate/Support/Js.php`
- `Schedule::command()` + `->thenWithOutput()` (used in `src/Providers/TotemServiceProvider.php::scheduleTotemTasks`): `https://laravel.com/docs/13.x/upgrade#scheduling` cross-ref `https://github.com/laravel/framework/blob/13.x/src/Illuminate/Console/Scheduling/Event.php`
- Notification channel contracts (`src/Notifications/TaskCompleted.php`): `https://laravel.com/docs/13.x/upgrade#notifications`

(If any anchor above does not resolve at review time, the upgrade guide has re-anchored during a Laravel minor — reviewer, flag for update.)

## Resolved versions on this branch

_Fill in from `composer show ...` after install:_
- `laravel/framework`: ___
- `orchestra/testbench`: ___
- `phpunit/phpunit`: ___
- `illuminate/support`: ___

## CI matrix

- **Blocking (8 combos):** PHP 8.3/8.4 × Laravel 12.*/13.* × prefer-lowest/prefer-stable.
- **Non-blocking (4 combos):** PHP 8.5 × Laravel 12.*/13.* × prefer-lowest/prefer-stable (`continue-on-error: true`).

The `prefer-lowest` lanes include a floor-verification step that fails if `illuminate/support` resolves below the declared constraint floor.

## Test plan

- [x] `composer validate --strict` clean.
- [x] `composer update --prefer-dist` resolves cleanly against Laravel 13.x stable.
- [x] Full PHPUnit suite passes locally on Laravel 13 / prefer-stable / PHP 8.3.
- [ ] CI matrix green on all 8 blocking combos.
- [ ] 8.5 experimental combos either pass or fail without blocking the merge.

## Design spec

See `docs/plans/2026-04-21-totem-v13-release-design.md` §2. ACs AC2.1–AC2.5.

## Release plan

No release triggered by this PR alone. v13.0.0 is tagged after PR 5 (release prep). This PR is one of four that precede the tag.
```

- [ ] **Step 3: Watch CI run**

Monitor the CI pipeline. Expected outcome:

- All 8 blocking lanes pass.
- 4 experimental 8.5 lanes either pass or fail with `continue-on-error: true` (the PR can merge regardless).
- The floor-verification step on `prefer-lowest` lanes passes without surfacing a floor violation.

If a blocking lane fails:
- **Laravel 12 prefer-lowest fail:** the code uses an API that landed after Laravel 12.0.0. Raise the floor: `"illuminate/bus": "^12.X|^13.0"` where X is the minor that introduced the API.
- **Laravel 13 prefer-stable fail:** a genuine L13 incompatibility. Diagnose the stack trace. If fixable in `src/`, split the fix into a separate PR.
- **PHPUnit-related fail on all lanes:** PHPUnit 12 has stricter deprecations than 10. Fix or bump floor.

- [ ] **Step 4: Backfill resolved versions in PR body**

After local install + CI resolution, edit the PR body to fill in the "Resolved versions" section with the actual versions composer picked.

---

## Task 9: After merge

Goal: keep the branch state clean for PR 3.

- [ ] **Step 1: Confirm merge + fetch**

After PR approval + merge to `13.x`:

```bash
git checkout 13.x
git pull --ff-only origin 13.x
git log -1 --format='%h %s'
```

- [ ] **Step 2: Delete the local branch**

```bash
git branch -d feat/v13-baseline
git push origin --delete feat/v13-baseline
```

- [ ] **Step 3: Start the compat spike (prerequisite for PR 3)**

Per spec §3.0, PR 3 (Pest migration) cannot open without a compat spike artifact. Create the spike branch now:

```bash
git checkout 13.x
git checkout -b spike/pest-testbench-l13-compat
```

The spike work is described in `docs/plans/2026-04-21-totem-v13-pest-migration-plan.md` Task 1 (Pest migration plan, Task 1 is the spike task).

No CHANGELOG edit in this PR. v13.0.0's CHANGELOG entry is drafted in PR 5 (release prep), which is why this PR doesn't touch it.

---

## Summary: Acceptance Criteria Coverage

| AC    | Task(s)    | Verification                                                                                     |
|-------|------------|--------------------------------------------------------------------------------------------------|
| AC2.1 | Task 4, 8  | `composer install` + local PHPUnit on Laravel 13 stable PHP 8.3; CI confirms remaining 7 combos. |
| AC2.2 | Task 8     | CI blocking matrix (8 combos) green.                                                             |
| AC2.3 | Task 5, 8  | Workflow has `continue-on-error: ${{ matrix.experimental == true }}` on 8.5 include entries.     |
| AC2.4 | Task 6     | README compat matrix + requirements + badge updated.                                             |
| AC2.5 | — (N/A)    | No changes to `src/` — `git diff src/` is empty for this PR (reviewer gate).                    |
