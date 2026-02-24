# Design: Upcoming Calendar View

**Date:** 2026-02-24
**Status:** Approved

## Problem

There is no way to see at a glance which tasks will run over the next 1–3 days. The existing Tasks list shows last-run stats and the next single upcoming run per task, but gives no sense of density, overlap, or schedule across a time window.

## Solution

Add an "Upcoming" page with an Outlook-style time-grid calendar showing all scheduled task runs over a 1-day or 3-day window. The backend computes run times from each task's cron expression; the frontend renders a pure CSS/Vue grid — no new JS dependencies.

## Architecture

### Routes

Two routes added to `web.php` inside the existing `tasks` group, before the `{totemTask}` wildcard:

```php
Route::get('upcoming', 'UpcomingTasksController@index')->name('totem.upcoming');
Route::get('upcoming/events', 'UpcomingTasksController@events')->name('totem.upcoming.events');
```

### Sidebar

A second nav item — "Upcoming" — added to `resources/views/partials/sidebar.blade.php`, linking to `totem.upcoming`.

### Files

- Pop stash: `resources/views/tasks/schedules.blade.php` → repurposed as the calendar Blade page
- Pop stash: `resources/assets/js/tasks/components/ScheduleRow.vue` → repurposed as `UpcomingCalendar.vue`
- Create: `src/Http/Controllers/UpcomingTasksController.php`
- Register Vue component in the existing app JS entry point

---

## Backend — UpcomingTasksController

### `index()`
Returns the Blade view `totem::tasks.schedules`. No data passed — Vue fetches everything via AJAX.

### `events()`

**Query parameters:**
- `start` — ISO 8601 timestamp (default: now, floored to current minute)
- `days` — integer, 1 or 3 (default: 1)

**Logic:**
1. Parse `$start` with Carbon, compute `$end = $start->copy()->addDays($days)`
2. Load all active tasks via `EloquentTaskRepository::findAllActive()`
3. For each task, loop using `CronExpression::factory($task->getCronExpression())->getNextRunDate($cursor)`, advancing `$cursor` to each result until `$cursor >= $end`
4. Collect events as `{ task_id, description, command, scheduled_at (ISO 8601) }`

**Response:**
```json
{
  "start": "2026-02-24T00:00:00+00:00",
  "end": "2026-02-25T00:00:00+00:00",
  "days": 1,
  "events": [
    { "task_id": 1, "description": "Send daily report", "command": "report:daily", "scheduled_at": "2026-02-24T08:00:00+00:00" }
  ]
}
```

All events shown — no truncation for high-frequency tasks.

---

## Frontend — UpcomingCalendar.vue

### State
- `currentStart` — Date, defaults to start of current hour
- `days` — integer, 1 or 3 (default: 1)
- `events` — array of event objects from API
- `loading` — boolean

### Grid Layout
CSS grid with `days + 1` columns:
- Column 1: time labels (00:00 – 23:00)
- Columns 2…n: one per day in the window

25 rows:
- Row 1: header row with date label per day column
- Rows 2–25: hourly slots 00:00–23:00

Event chips are placed in the cell matching their day column and hour row. Multiple events in the same cell stack vertically. Each chip shows the task description (truncated ~20 chars) and exact run time (HH:mm).

### Controls
- **1-day / 3-day toggle** — updates `days`, re-fetches
- **Prev / Next arrows** — shift `currentStart` by `days` days, re-fetches
- **Today button** — resets `currentStart` to now, re-fetches
- **Loading spinner** — shown while fetch in progress (UIKit spinner)
- **Error alert** — UIKit alert on fetch failure

### Data Flow
On `mounted()` and whenever `currentStart` or `days` changes (watcher), fetch:
```
GET /totem/tasks/upcoming/events?start=<ISO>&days=<1|3>
```
Populate `events` from response. Frontend performs no cron computation — display only.

---

## Non-Goals
- Condensing/grouping high-frequency tasks (deferred)
- Click-through to task detail from calendar chip (can be added later — chips link to `totem.task.view`)
- Timezone selector (uses server timezone)
