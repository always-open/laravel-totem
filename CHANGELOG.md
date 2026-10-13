# Laravel Totem Change Log

This project follows [Semantic Versioning](CONTRIBUTING.md).

---

## v13.4.0 - 10/13/2026

### Added

- Dark mode for the dashboard. A theme switcher in the sidebar offers **Light**, **Dark**, and **Browser default** (follows the operating system, and is the starting choice). The choice is stored in the browser's `localStorage` under `totem.web.theme_storage_key` (`TOTEM_THEME_STORAGE_KEY`, default `totem-theme`); set it to `theme` to share the preference with Filament's theme switcher. The theme applies before first paint. Re-publish assets with `php artisan totem:assets` to get the new `css/dark.css`. (PR #425)

## v13.3.0 - 10/08/2026

### Added

- Pause the scheduled runs of all tasks at once, from the task list (**Pause All** / **Resume**) or with `php artisan totem:pause [--until=...]` and `php artisan totem:resume`. A pause can resume automatically after a set time. Each task's own enabled/disabled setting is left untouched, and manual execution still works while paused. Requires the new `schedule_pauses` migration. (PR #424)

## v13.2.0 - 10/01/2026

### Changed

- CI tests PHP 8.5, and its jobs are required to pass. (PR #423)

## v13.1.1 - 09/29/2026

### Changed

- The Composer package is renamed from `studio/laravel-totem` to `always-open/laravel-totem`. Update the name in your `composer.json`; the `Studio\Totem` PHP namespace is unchanged.

## v13.1.0 - 09/29/2026

Brings the v12.1.0 changes to 13.x. (PR #422)

## v13.0.0 - 05/11/2026

### Changed

- Adds Laravel 13 support and drops Laravel 10 and 11. Requires PHP 8.3 or later. (PR #417)

## v12.1.0 - 09/29/2026

### Changed

- Task search is case-insensitive. (PR #420)
- The task list searches as you type. (PR #421)

### Fixed

- A task whose cron expression never matches a calendar date (for example `0 0 31 2 *`) no longer breaks the task list, the task view, the JSON export, or `schedule:list`. The parser throws `RuntimeException('Impossible CRON expression')` after a bounded search; `Task::$upcoming` is now `null` for such a task and every surface renders it as `Never`. The scheduler itself was never affected, since it matches the current minute rather than searching forward. `Totem::nextRunDate()` is the shared helper. (PR #419)

## v12.0.2 - 04/21/2026

### Fixed

- Empty output popup on the task-view page. The `<task-output>` Vue component received an empty `:output` prop because `@json()` produced a JSON literal whose outer `"` delimiters collided with the HTML attribute's own `"` wrappers, making the rendered HTML malformed. Replaced with `Js::from()`, Laravel's purpose-built helper for embedding JS expressions inside HTML attributes. (PR #415)

## v11.0.0 - 03/27/2024

- Add Laravel 11.x Support
- Update Change Log

## v10.0.0 - 03/17/2023

- Add Laravel 10.x Support
- Update Change Log

## v9.0.0 - 06/29/2022

- Add Laravel 9.x Support
- Update Change Log

## v8.0.0 - 10/30/2020

- Add Laravel 8.x Support
- Update Change Log

## v7.0.0 - 04/17/2020

- Add Laravel 7.x Support
- Update Change Log

## v6.0.0 - 10/26/2019

- Add Laravel 6.x Support
- Update Change Log

## v5.0.0 - 03/03/2019

- Add Laravel 5.8 Support
- Update Change Log

## v4.0.3 - 01/24/2019

- Fix constant definition typo in service provider

## v4.0.2 - 12/20/2018

- Add support for configurable DB connection
- Cache table prefix to avoid multiple and frequent DB queries

## v4.0.1 - 11/08/2018

- Fix table prefix issue that surfaced with Laravel 5.7.12
- Fix foreign key database migrations

## v4.0.0 - 09/05/2018

- Add Laravel 5.7 compatiblity
- Use null coalescing operator in blade file instead of or operator
- Update changelog and readme

## v3.0.0 - 02/27/2018

- Add support for Laravel 5.6

## v2.0.0 - 09/03/2017

- Add support for Laravel 5.5

## v1.0.0 - 09/03/2017

- Initial stable release for Laravel 5.4
