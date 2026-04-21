# Totem v13.0.0 Release Prep (PR 5) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Finalize user-facing documentation (README, CHANGELOG with Keep-a-Changelog format), verify the `league/commonmark` CVE exposure analysis is still accurate against the resolved Laravel 13.x version, confirm the v13.1 follow-up ticket exists with a live URL, and tag `v13.0.0`.

**Architecture:** Three tasks of documentation + one tag operation + two verification gates. No code changes in `src/` or `tests/`. The PR is pure release plumbing.

**Tech Stack:** Markdown (README, CHANGELOG). Git tag. GitHub Actions CI. Branch: `release/v13.0.0` off `13.x`. Prerequisites: PRs 3 and 4 merged.

**Spec:** `docs/plans/2026-04-21-totem-v13-release-design.md` §5 (Section 5: PR 5). ACs AC5.1–AC5.7.

---

## File Structure

| File | Action |
|------|--------|
| `README.md` | Modify (finalize compat matrix, requirements, badge, test commands) |
| `CHANGELOG.md` | Modify (promote to Keep a Changelog 1.1.0 format; add v13.0.0 + backfill v12.0.2 entries) |
| `resources/views/tasks/view.blade.php` | Read-only (verify PR 3 forward-port landed) |
| `src/Notifications/TaskCompleted.php` | Read-only (verify commonmark analysis in spec §7 is still accurate) |

---

## Task 1: Prerequisite verification

Goal: confirm PRs 2, 3, and 4 are all merged onto `13.x` and the tree is ready for release.

- [ ] **Step 1: Branch and tree state**

```bash
git checkout 13.x
git pull --ff-only origin 13.x
git log --oneline 13.x ^$(git merge-base 13.x 12.x) | head -20
```

Expected: recent commits include (in some order) the merged PR 2 (baseline), PR 3 (Pest migration + popup forward-port), and PR 4 (type sweep).

Sanity-check each prerequisite:

```bash
# PR 2: composer floors should be ^8.3 / ^12.0|^13.0
grep -A1 '"php":' composer.json | head -2

# PR 3: pest should be in require-dev, phpunit should NOT be
grep '"pestphp/pest"' composer.json
grep '"phpunit/phpunit"' composer.json

# PR 3: popup fix should be applied
grep ':output=' resources/views/tasks/view.blade.php

# PR 4: type sweep should have landed — spot-check Executed.php
grep 'float \$started' src/Events/Executed.php
```

Expected:
- `"php": "^8.3"` present.
- `"pestphp/pest"` present; `"phpunit/phpunit"` NOT in composer.json.
- Blade line uses `Js::from(` not `@json(`.
- `Executed.php` has `float $started` (type sweep landed).

If any check fails, STOP — the required PRs have not all merged. Do not proceed to release prep.

- [ ] **Step 2: Create the release branch**

```bash
git checkout -b release/v13.0.0
```

- [ ] **Step 3: Verify DB_HOST is local and tests pass on current tip**

```bash
grep '^DB_HOST' .env 2>/dev/null || echo "no .env — testbench uses sqlite :memory:"
APP_ENV=testing vendor/bin/pest
```

Expected: all tests pass. This is the last full-suite check before tagging.

---

## Task 2: Verify commonmark CVE analysis is still accurate (spec §7.4)

Goal: per spec §7.4, re-verify the commonmark reachability analysis against the Laravel 13.x version actually resolved on this branch. The analysis claims the affected extensions (`embed`, `DisallowedRawHtml`) are NOT enabled in Laravel's default mail configuration. Verify before shipping.

- [ ] **Step 1: Identify the resolved Laravel version**

```bash
composer show laravel/framework | grep '^versions' | head -1
```

Record the version (e.g., `v13.6.0`).

- [ ] **Step 2: Inspect Laravel's Mail Markdown class**

```bash
ls vendor/laravel/framework/src/Illuminate/Mail/Markdown.php
cat vendor/laravel/framework/src/Illuminate/Mail/Markdown.php | head -80
```

Look for `CommonMarkConverter` instantiation or `Environment` setup. Specifically check:

- Does the file call `addExtension(new EmbedExtension(...))` anywhere? (It should NOT.)
- Does the file call `addExtension(new DisallowedRawHtmlExtension(...))`? (It should NOT.)
- What is the default config passed to the converter?

```bash
grep -n 'addExtension\|CommonMarkConverter\|Environment::create\|Embed\|DisallowedRawHtml' vendor/laravel/framework/src/Illuminate/Mail/Markdown.php
```

Expected: either no matches for `Embed` / `DisallowedRawHtml`, or the file uses only default extensions (GitHubFlavoredMarkdown, etc.).

If EITHER extension is enabled by default in the resolved Laravel version, the spec §7 analysis is INVALIDATED. STOP and either:

- Bump the transitive dep via an explicit `league/commonmark: ^2.8.2` (or whatever is the current patched version) entry in `composer.json` require-dev (not require — transitive) or a constraint conflict.
- Rewrite spec §7 and §5.3 to reflect the new exposure, and draft a revised release-notes paragraph.

If NEITHER extension is enabled (expected case), proceed.

- [ ] **Step 3: Verify Totem does not enable custom extensions**

```bash
grep -rn 'addExtension\|Embed\|DisallowedRawHtml' src/
```

Expected: no matches. Totem does not touch commonmark's extension registry.

- [ ] **Step 4: Record the verification result**

In the §9 Status section of the design spec, check off the "§7.3 commonmark citation — re-verified at PR 5 time" box. This is done by editing the spec file in a documentation-only way:

Edit `docs/plans/2026-04-21-totem-v13-release-design.md` §9 Status section. Change:

```markdown
- [ ] §7.3 commonmark citation — re-verified at PR 5 time against resolved Laravel 13.x minor (§7.4)
```

to:

```markdown
- [x] §7.3 commonmark citation — re-verified at PR 5 time against resolved Laravel 13.x minor (§7.4). Confirmed: Laravel <resolved-version>'s `Illuminate\Mail\Markdown` does not register EmbedExtension or DisallowedRawHtmlExtension; Totem does not configure commonmark extensions. Analysis holds.
```

Fill `<resolved-version>` with the string from Step 1 (e.g., `v13.6.0`).

- [ ] **Step 5: Stage the spec update**

```bash
git add docs/plans/2026-04-21-totem-v13-release-design.md
```

Don't commit yet — batch with the README/CHANGELOG updates in Task 5.

---

## Task 3: Verify and finalize the follow-up ticket URL (AC5.7)

Goal: the browser-tests deferral to v13.1 is a blocking precondition for the tag.

- [ ] **Step 1: Confirm the follow-up issue exists**

This is a human/team action (opening an issue on GitHub or a ticket in your tracker). If not already done:

```bash
gh issue create \
  --title "v13.1: Add Pest browser test infrastructure (deferred from v13.0)" \
  --body "Per v13 design doc §6, browser test infrastructure (Pest browser plugin + Playwright) was deferred from v13.0 to v13.1 because the Pest browser ecosystem was not stable enough to pin with confidence at v13.0 planning time.

Scope:
- Validated Pest browser plugin choice + version pin.
- Playwright + chromium in CI.
- Testbench HTTP server lifecycle (see design doc §6.2 for the fallback approach).
- Three browser tests: popup regression (load-bearing), execute button flow, dashboard smoke.
- Browser CI lane: PHP 8.3 × Laravel 12.x × prefer-stable.

See docs/plans/2026-04-21-totem-v13-release-design.md §6 for full context.

## Target date

TBD at v13.1 planning kickoff." \
  --label enhancement
```

Capture the issue URL from the output (e.g., `https://github.com/always-open/laravel-totem/issues/XXX`).

- [ ] **Step 2: Record the URL**

Save the URL to a scratch note — it will be pasted into (a) the CHANGELOG v13.0.0 entry, (b) the v13 design doc §9 Status, (c) the PR 5 body.

- [ ] **Step 3: Verify the URL resolves**

```bash
URL="https://github.com/always-open/laravel-totem/issues/XXX"  # replace with actual
curl -sSI "$URL" | head -1
```

Expected: `HTTP/2 200`. If 404, the issue doesn't exist (typo, or not yet created). STOP.

- [ ] **Step 4: Update the design doc §9 Status**

Edit `docs/plans/2026-04-21-totem-v13-release-design.md` §9. Change:

```markdown
- [ ] Follow-up ticket for PR F (browser tests, v13.1) — URL pending. **Required live before `git tag v13.0.0` (AC5.7).**
```

to:

```markdown
- [x] Follow-up ticket for PR F (browser tests, v13.1) — <ISSUE_URL>. Verified live (HTTP 200) before tag (AC5.7).
```

Replace `<ISSUE_URL>` with the actual URL from Step 1.

Stage (will batch commit later):

```bash
git add docs/plans/2026-04-21-totem-v13-release-design.md
```

---

## Task 4: Update README

Goal: finalize the README for v13.0.0 — the matrix, requirements, badge, test commands, and browser-tests deferral line.

**Files:**
- Modify: `README.md`

- [ ] **Step 1: Verify compat matrix is up-to-date from PR 2**

```bash
grep -A8 '#### Compatibility Matrix' README.md
grep -A6 '#### Requirements' README.md
```

Expected: compat matrix has 6 rows ending with `10.x | 11.x`; requirements say `PHP 8.3+` and `Laravel 12.x or 13.x`. This was done in PR 2. If anything's missing, fix it here.

- [ ] **Step 2: Check the build badge**

```bash
grep 'badge.svg?branch=' README.md
```

Expected: `badge.svg?branch=13.x`. If it's `12.x` or older, fix:

Before:
```html
<img src="https://github.com/always-open/laravel-totem/workflows/Laravel/badge.svg?branch=12.x" alt="Build Status">
```

After:
```html
<img src="https://github.com/always-open/laravel-totem/workflows/Laravel/badge.svg?branch=13.x" alt="Build Status">
```

- [ ] **Step 3: Find or add a "Running Tests" section**

```bash
grep -n 'test\|Testing\|Pest\|PHPUnit' README.md
```

Check whether an explicit "Running Tests" section exists. If yes, update it. If no, add one.

The test section content:

```markdown
#### Running Tests

Totem's test suite uses [Pest 3](https://pestphp.com/):

```bash
APP_ENV=testing vendor/bin/pest
```

Browser-level regression tests are deferred to v13.1 (see [follow-up issue](<ISSUE_URL>)).
```

Replace `<ISSUE_URL>` with the URL from Task 3.

Insert this section after the existing "Updating" section or similar, maintaining the README's structural flow.

- [ ] **Step 4: Verify all README edits**

```bash
grep 'badge.svg?branch=13.x' README.md  # must return the line
grep 'vendor/bin/pest' README.md         # must return 1+ lines
grep 'browser-level' README.md           # must return 1 line (case-insensitive)
```

- [ ] **Step 5: Stage the README**

```bash
git add README.md
```

Don't commit yet — batch with CHANGELOG in Task 6.

---

## Task 5: Promote CHANGELOG to Keep a Changelog 1.1.0 format

Goal: the v13 release promotes the CHANGELOG from the legacy `## vX.Y.Z - MM/DD/YYYY` format to Keep a Changelog 1.1.0 (`## [X.Y.Z] - YYYY-MM-DD` with Added/Changed/Removed/Fixed/Deprecated subsections). Backfill the v12.0.2 entry and add the v13.0.0 entry.

**Files:**
- Modify: `CHANGELOG.md`

- [ ] **Step 1: Read the current CHANGELOG**

```bash
head -20 CHANGELOG.md
```

Expected current top (from PR 1 on 12.x, but this branch is 13.x — so it might not have the v12.0.2 entry from PR 1 yet because that lived on 12.x):

```markdown
# Laravel Totem Change Log

This project follows [Semantic Versioning](CONTRIBUTING.md).

---

## v11.0.0 - 03/27/2024
...
```

If the v12.0.0 / v12.0.1 / v12.0.2 entries from the `12.x` branch are NOT on `13.x`, they need to be backfilled during this PR. (13.x was branched from the 12.x modernization tip, but newer 12.x commits like v12.0.2 may not have reached 13.x.)

Cherry-pick or manually backfill:

```bash
# Check whether the 12.x changelog has entries the 13.x one lacks
git log 12.x -- CHANGELOG.md --oneline | head -10
git diff 13.x..12.x -- CHANGELOG.md | head -60
```

If there's a diff, it's the v12.x entries missing on 13.x. Backfill them in the new format during Step 2.

- [ ] **Step 2: Rewrite the top of CHANGELOG.md**

Replace the existing header + top entries with the promoted format. Target state (lines 1-80+):

```markdown
# Laravel Totem Change Log

This project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html) and the changelog format adheres to [Keep a Changelog 1.1.0](https://keepachangelog.com/en/1.1.0/).

All notable changes to this project are documented in this file.

---

## [13.0.0] - YYYY-MM-DD

### Added

- Laravel 13 support, verified against `laravel/framework` v13.0.0+. (See PR ##### for upgrade-guide citations.)
- PHP 8.5 in CI matrix as a non-blocking experimental lane with documented promotion criteria.
- `prefer-lowest` CI lane to verify declared composer floors actually resolve against the minimum constraint set.
- Array-shape PHPDoc annotations on `EloquentTaskRepository::store()`, `update()`, and `import()` documenting the input contracts.

### Changed

- Minimum PHP version is now 8.3 (was 8.2).
- Test suite migrated from PHPUnit (class-based) to [Pest 3](https://pestphp.com/) (functional). The `tests/TestCase.php` base class is preserved via `uses(TestCase::class)` in `tests/Pest.php`.
- Parameter and return type hints added across `src/`. Untyped `@param` PHPDoc entries tightened or removed.

### Removed

- Laravel 10 and 11 support. Users on Laravel 10.x should use Totem 11.x; users on Laravel 11.x should use Totem 12.x.
- PHP 8.2 support.
- `phpunit/phpunit` from `require-dev` (bundled transitively by Pest 3).

### Fixed

- Empty task-output popup on the task-view page (forward-ported from v12.0.2). `@json()` was swapped for `Js::from()` to produce HTML-attribute-safe JS expressions.

### Deferred

- Browser test infrastructure deferred to v13.1. See [follow-up issue](<ISSUE_URL>) for the tracking ticket and target date.

### Note on `composer audit` findings

Installing Totem v13.0.0 surfaces two medium-severity advisories in `league/commonmark` (CVE-2026-33347 and CVE-2026-30838, both in `league/commonmark` ≤ 2.8.1). `league/commonmark` is a transitive dependency of `laravel/framework`; Totem does not require it directly. The advisories affect the `embed` and `DisallowedRawHtml` commonmark extensions, which are **opt-in** and **not enabled** in Laravel's default mail rendering. Totem's notification code (`src/Notifications/TaskCompleted.php`) uses `MailMessage->line()`, which passes through Laravel's default markdown pipeline without enabling either affected extension. The CVE code paths are therefore unreachable from Totem's runtime. Downstream users who need to clear the `composer audit` output can suppress these specific advisories via `composer audit --ignore` or wait for the transitive bump in a future `laravel/framework` release.

## [12.0.2] - YYYY-MM-DD

### Fixed

- Empty output popup on the task-view page. The `<task-output>` Vue component received an empty `:output` prop because `@json()` produced a JSON literal whose outer `"` delimiters collided with the HTML attribute's own `"` wrappers, making the rendered HTML malformed. Replaced with `Js::from()`. (PR #####)

## [12.0.1] - MM/DD/YYYY

_Prior entry — legacy format; see the repository history on the `12.x` branch for detail._

## [12.0.0] - MM/DD/YYYY

_Prior entry — legacy format; see the repository history on the `12.x` branch for detail._

## v11.0.0 - 03/27/2024

- Add Laravel 11.x Support
- Update Change Log

...
```

Placeholders to resolve:
- `YYYY-MM-DD` on v13.0.0 entry — the release date (today, when tagging).
- `YYYY-MM-DD` on v12.0.2 entry — the date v12.0.2 was tagged on `12.x` (from `git log` on 12.x).
- `<ISSUE_URL>` — from Task 3.
- `#####` placeholder PR numbers — filled after PR 5 is opened and v13.0.0 tag is created. For v12.0.2 PR number, look it up on the `12.x` branch history.

Pre-v12.0.2 entries (v12.0.1, v12.0.0, and older) stay in their existing format. Promotion to Keep-a-Changelog is prospective; no effort is spent rewriting historical entries. The `## v11.0.0 - 03/27/2024` form coexists with the new `## [X.Y.Z] - YYYY-MM-DD` form — mixing the two is acceptable for an incremental promotion.

- [ ] **Step 3: Fill in the PR 5 PR number placeholder**

After opening PR 5 (Task 7), the PR will have a number assigned. Edit `CHANGELOG.md` to replace `##### for upgrade-guide citations` with the actual PR 5 URL.

This has to happen after the PR is open. Add a TODO note in the commit message to do this.

- [ ] **Step 4: Resolve v12.0.2 date and PR number**

```bash
git log 12.x --oneline --grep='v12.0.2' -1
git log --all --format='%ai' --grep='v12.0.2' -1
```

Fill in the v12.0.2 `YYYY-MM-DD` and PR number from the output.

- [ ] **Step 5: Stage the CHANGELOG**

```bash
git add CHANGELOG.md
```

---

## Task 6: Commit the release-prep changes

Goal: batch the README, CHANGELOG, and spec-status updates into one or two cohesive commits.

- [ ] **Step 1: Review the staged changes**

```bash
git diff --cached --stat
git diff --cached
```

Expected files staged:
- `README.md` (badge, test section, possible browser deferral line)
- `CHANGELOG.md` (promoted format, v13.0.0 + v12.0.2 backfill)
- `docs/plans/2026-04-21-totem-v13-release-design.md` (§9 status checkboxes)

- [ ] **Step 2: Commit as one**

```bash
git commit -m "chore: prepare v13.0.0 release (README, CHANGELOG, spec status)"
```

If you prefer finer commits, split into three:

```bash
git commit README.md -m "docs: finalize README for v13.0.0"
git commit CHANGELOG.md -m "docs: add v13.0.0 entry and promote to Keep a Changelog format"
git commit docs/plans/2026-04-21-totem-v13-release-design.md -m "docs: check v13 design doc status boxes"
```

Either is fine — the release is the goal, not commit aesthetics.

---

## Task 7: Push, open PR, and CI

- [ ] **Step 1: Push the branch**

```bash
git push -u origin release/v13.0.0
```

- [ ] **Step 2: Open the PR against `13.x`**

Title: `release: prep v13.0.0`

Body:

```markdown
## Summary

Release-prep PR for v13.0.0. No `src/` changes. Finalizes:

- README compat matrix, requirements, build badge, and "Running Tests" section.
- CHANGELOG promoted to Keep a Changelog 1.1.0 format with v13.0.0 and v12.0.2 entries.
- v13 design doc §9 status checkboxes finalized (commonmark re-verification + follow-up ticket URL).

## Prerequisites confirmed

- [x] PR 2 (baseline) merged — composer floors at `^8.3` / `^12.0|^13.0`.
- [x] PR 3 (Pest migration) merged — `vendor/bin/pest` is the CI command; `phpunit/phpunit` is gone from `composer.json`; v12.0.2 popup fix is forward-ported.
- [x] PR 4 (type sweep) merged — `src/Events/Executed.php` has `float $started` / `string $output`; repository array shapes documented.

## commonmark CVE re-verification (AC5.7 / spec §7.4)

Against resolved `laravel/framework` <FILL_VERSION>:
- `vendor/laravel/framework/src/Illuminate/Mail/Markdown.php` does NOT register `EmbedExtension` or `DisallowedRawHtmlExtension`.
- `src/Notifications/TaskCompleted.php` does NOT configure commonmark extensions.
- Conclusion: §7 analysis holds. The release-notes paragraph in §5.3 ships verbatim in the CHANGELOG v13.0.0 `### Note on composer audit findings` subsection.

## Follow-up ticket for v13.1 browser tests

URL: <ISSUE_URL>

Verified live via `curl -sSI` (HTTP 200). Referenced in:
- CHANGELOG v13.0.0 `### Deferred`.
- README "Running Tests" section.
- Spec §9 Status.

## Test plan

- [x] Full Pest suite green on current `13.x` tip before branching.
- [x] No code changes to `src/` or `tests/` in this PR.
- [ ] CI matrix green on all 8 blocking combos.
- [ ] Pre-tag: all AC5.1–AC5.7 checkboxes confirmed.

## Tagging plan

After merge:
1. Fast-forward local `13.x`.
2. Run full Pest suite one last time.
3. `git tag v13.0.0`
4. `git push origin v13.0.0`
5. Create a GitHub release pointing at the tag, pasting the CHANGELOG v13.0.0 entry as the body.

## Design spec

See `docs/plans/2026-04-21-totem-v13-release-design.md` §5. ACs AC5.1–AC5.7.
```

- [ ] **Step 3: Capture the PR number**

After opening, note the PR number (e.g., #XXX). Edit the CHANGELOG to replace the `#####` placeholder in the v13.0.0 entry with the PR URL:

```bash
# Edit CHANGELOG.md: replace the placeholder
sed -i '' 's|PR #####|PR #XXX|' CHANGELOG.md  # macOS; Linux drops the ''
git add CHANGELOG.md
git commit -m "docs: fill v13.0.0 PR reference in CHANGELOG"
git push
```

- [ ] **Step 4: CI**

Expected: all 8 blocking combos green. If anything fails, diagnose before proceeding to tag.

---

## Task 8: Tag v13.0.0

Goal: create the tag and push it. This is the release moment.

- [ ] **Step 1: Final AC verification**

Before the tag, tick off every AC one last time:

| AC     | Verified? | Evidence                                                                                       |
|--------|-----------|------------------------------------------------------------------------------------------------|
| AC5.1  | [ ]       | README compat matrix + requirements + badge reflect final scope.                              |
| AC5.2  | [ ]       | CHANGELOG has v12.0.2 and v13.0.0 entries in Keep a Changelog 1.1.0 format.                   |
| AC5.3  | [ ]       | CHANGELOG v13.0.0 entry contains the §5.3 release-notes paragraph verbatim.                   |
| AC5.4  | [ ]       | Follow-up ticket URL pasted in CHANGELOG and README.                                           |
| AC5.5  | [ ]       | CI blocking matrix green (PR 5's own CI run).                                                 |
| AC5.6  | [ ]       | Ready to `git tag v13.0.0`.                                                                    |
| AC5.7  | [ ]       | Follow-up ticket URL resolves HTTP 200 (verified in Task 3).                                  |

If any box is unchecked, STOP and resolve before tagging.

- [ ] **Step 2: After PR 5 merges, fast-forward and final verify**

```bash
git checkout 13.x
git pull --ff-only origin 13.x
git log -1 --format='%h %s'

# Final test-suite run at the to-be-tagged commit
APP_ENV=testing vendor/bin/pest
```

Expected: test suite green. HEAD is the merge commit for PR 5.

- [ ] **Step 3: Tag**

```bash
git tag v13.0.0
```

- [ ] **Step 4: Push the tag**

```bash
git push origin v13.0.0
```

- [ ] **Step 5: Verify Packagist auto-sync**

Check `https://packagist.org/packages/studio/laravel-totem`. Within a few minutes, `v13.0.0` should appear. If Packagist lags beyond 10 minutes, click the "Update" button on the package page.

- [ ] **Step 6: Create the GitHub release**

```bash
# Extract the v13.0.0 CHANGELOG section for the release body
awk '/^## \[13\.0\.0\]/,/^## \[/{ if (/^## \[/ && !/^## \[13\.0\.0\]/) exit; print }' CHANGELOG.md > /tmp/release-body.md

gh release create v13.0.0 \
  --title "v13.0.0" \
  --notes-file /tmp/release-body.md
```

Expected: a new GitHub release page with the v13.0.0 changelog body.

- [ ] **Step 7: Announce (optional, human-only)**

- Post a note in the package's issue tracker / discussions.
- Update any internal dashboards tracking v12/v13 adoption.
- Start the v13.1 planning ticket (the follow-up issue from Task 3).

---

## Summary: Acceptance Criteria Coverage

| AC    | Task(s)     | Verification                                                                              |
|-------|-------------|-------------------------------------------------------------------------------------------|
| AC5.1 | Task 4      | README compat matrix + requirements + badge + test instructions updated.                  |
| AC5.2 | Task 5      | CHANGELOG has v12.0.2 (backfilled) and v13.0.0 (new) entries in Keep a Changelog 1.1.0 format. |
| AC5.3 | Task 5      | v13.0.0 CHANGELOG entry's `### Note on composer audit findings` matches spec §5.3 verbatim. |
| AC5.4 | Task 3      | Follow-up ticket URL resolves (verified via `curl -sSI`); pasted in CHANGELOG and README.  |
| AC5.5 | Task 7      | CI blocking matrix (8 combos) green on PR 5's own CI run.                                  |
| AC5.6 | Task 8      | `git tag v13.0.0` created on `13.x` HEAD and pushed.                                       |
| AC5.7 | Tasks 3, 8  | Follow-up ticket URL is pasted in the CHANGELOG v13.0.0 entry BEFORE `git tag` (verified in Task 8 Step 1 AC table). |
