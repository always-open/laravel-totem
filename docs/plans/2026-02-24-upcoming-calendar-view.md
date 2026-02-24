# Upcoming Calendar View Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add an Outlook-style time-grid calendar page showing all upcoming scheduled task runs over a 1-day or 3-day window, pageable forward and backward.

**Architecture:** A new `UpcomingTasksController` exposes two routes: a Blade page (`/totem/tasks/upcoming`) and a JSON events endpoint (`/totem/tasks/upcoming/events`). The endpoint loops through all active tasks using `CronExpression::factory()` to generate every run within the requested window, returning them as a flat JSON array. A `UpcomingCalendar.vue` Vue component fetches from that endpoint and renders a CSS grid calendar — no new JS dependencies (axios is already global via bootstrap.js, moment is already imported in app.js).

**Tech Stack:** PHP/Laravel (CronExpression, Carbon), Vue 2, UIKit 3, axios (global), moment.js (global), PHPUnit + Orchestra Testbench.

---

### Task 1: Controller, routes, Blade view, sidebar

**Files:**
- Create: `src/Http/Controllers/UpcomingTasksController.php`
- Modify: `routes/web.php`
- Modify: `resources/views/partials/sidebar.blade.php`
- Modify: `resources/views/tasks/schedules.blade.php` (popped from stash)
- Create: `tests/Feature/UpcomingTasksTest.php`

**Step 1: Pop the stash**

```bash
git stash pop
```

This restores two files:
- `resources/assets/js/tasks/components/ScheduleRow.vue`
- `resources/views/tasks/schedules.blade.php`

Both files exist now but their content will be replaced in Task 2 (Vue) and this task (Blade).

**Step 2: Write the failing tests**

Create `tests/Feature/UpcomingTasksTest.php`:

```php
<?php

namespace Studio\Totem\Tests\Feature;

use Studio\Totem\Task;
use Studio\Totem\Tests\TestCase;

class UpcomingTasksTest extends TestCase
{
    public function test_user_can_view_upcoming_page()
    {
        $this->signIn();

        $response = $this->get(route('totem.upcoming'));

        $response->assertStatus(200);
    }

    public function test_guest_cannot_view_upcoming_page()
    {
        $response = $this->get(route('totem.upcoming'));

        $response->assertStatus(403);
    }

    public function test_events_endpoint_returns_json_structure()
    {
        $this->signIn();

        $response = $this->getJson(route('totem.upcoming.events', [
            'start' => '2026-01-01T00:00:00+00:00',
            'days'  => 1,
        ]));

        $response->assertStatus(200)
            ->assertJsonStructure(['start', 'end', 'days', 'events']);
    }

    public function test_events_endpoint_includes_active_task_runs()
    {
        $this->signIn();

        $task = Task::factory()->create(['expression' => '0 8 * * *']);

        $response = $this->getJson(route('totem.upcoming.events', [
            'start' => '2026-01-01T00:00:00+00:00',
            'days'  => 1,
        ]));

        $response->assertStatus(200);

        $taskIds = collect($response->json('events'))->pluck('task_id');
        $this->assertTrue($taskIds->contains($task->id));
    }

    public function test_events_endpoint_excludes_inactive_tasks()
    {
        $this->signIn();

        $inactive = Task::factory()->create([
            'expression' => '0 8 * * *',
            'is_active'  => false,
        ]);

        $response = $this->getJson(route('totem.upcoming.events', [
            'start' => '2026-01-01T00:00:00+00:00',
            'days'  => 1,
        ]));

        $taskIds = collect($response->json('events'))->pluck('task_id');
        $this->assertFalse($taskIds->contains($inactive->id));
    }

    public function test_events_endpoint_respects_window_boundary()
    {
        $this->signIn();

        // Daily at 8am — one hit in a 1-day window starting midnight Jan 1
        $task = Task::factory()->create(['expression' => '0 8 * * *']);

        $response = $this->getJson(route('totem.upcoming.events', [
            'start' => '2026-01-01T00:00:00+00:00',
            'days'  => 1,
        ]));

        $events = collect($response->json('events'))->where('task_id', $task->id);
        $this->assertCount(1, $events);
    }

    public function test_events_endpoint_defaults_to_one_day()
    {
        $this->signIn();

        $response = $this->getJson(route('totem.upcoming.events'));

        $response->assertStatus(200)
            ->assertJsonPath('days', 1);
    }
}
```

**Step 3: Run tests to confirm they fail**

```bash
./vendor/bin/phpunit tests/Feature/UpcomingTasksTest.php
```

Expected: All 6 tests FAIL (route `totem.upcoming` not found).

**Step 4: Create UpcomingTasksController**

Create `src/Http/Controllers/UpcomingTasksController.php`:

```php
<?php

namespace Studio\Totem\Http\Controllers;

use Carbon\Carbon;
use Cron\CronExpression;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Studio\Totem\Contracts\TaskInterface;

class UpcomingTasksController extends Controller
{
    private TaskInterface $tasks;

    public function __construct(TaskInterface $tasks)
    {
        parent::__construct();

        $this->tasks = $tasks;
    }

    public function index(): View
    {
        return view('totem::tasks.schedules');
    }

    public function events(Request $request): JsonResponse
    {
        $start = $request->has('start')
            ? Carbon::parse($request->input('start'))
            : Carbon::now()->startOfMinute();

        $days = (int) $request->input('days', 1);
        $days = in_array($days, [1, 3]) ? $days : 1;
        $end = $start->copy()->addDays($days);

        $events = [];

        $this->tasks->findAllActive()->each(function ($task) use ($start, $end, &$events) {
            try {
                $cron = CronExpression::factory($task->getCronExpression());
                $cursor = $start->copy();

                while (true) {
                    $next = Carbon::instance($cron->getNextRunDate($cursor));
                    if ($next >= $end) {
                        break;
                    }
                    $events[] = [
                        'task_id'      => $task->id,
                        'description'  => $task->description,
                        'command'      => $task->command,
                        'scheduled_at' => $next->toIso8601String(),
                    ];
                    $cursor = $next;
                }
            } catch (\Exception $e) {
                // Skip tasks with unparseable cron expressions
            }
        });

        return response()->json([
            'start'  => $start->toIso8601String(),
            'end'    => $end->toIso8601String(),
            'days'   => $days,
            'events' => $events,
        ]);
    }
}
```

**Step 5: Add routes**

In `routes/web.php`, add two routes inside the `tasks` group, immediately after the `import` route and **before** the `{totemTask}` wildcard:

```php
Route::get('upcoming', 'UpcomingTasksController@index')->name('totem.upcoming');
Route::get('upcoming/events', 'UpcomingTasksController@events')->name('totem.upcoming.events');
```

The file should look like this around that section:

```php
Route::get('export', 'ExportTasksController@index')->name('totem.tasks.export');
Route::post('import', 'ImportTasksController@index')->name('totem.tasks.import');

Route::get('upcoming', 'UpcomingTasksController@index')->name('totem.upcoming');
Route::get('upcoming/events', 'UpcomingTasksController@events')->name('totem.upcoming.events');

Route::get('{totemTask}', 'TasksController@view')->name('totem.task.view');
```

**Step 6: Update the Blade view**

Replace the entire content of `resources/views/tasks/schedules.blade.php` with:

```blade
@extends("totem::layout")
@section('page-title')
    @parent
    - Upcoming
@stop
@section('title')
    <h4 class="uk-card-title uk-margin-remove">Upcoming Schedule</h4>
@stop
@section('main-panel-content')
    <upcoming-calendar events-url="{{ route('totem.upcoming.events') }}"></upcoming-calendar>
@stop
```

**Step 7: Update sidebar**

In `resources/views/partials/sidebar.blade.php`, add the Upcoming nav item after the existing Tasks `<li>`:

```html
<li class="{{ request()->routeIs('totem.upcoming') ? 'uk-active' : '' }}">
    <a href="{{route('totem.upcoming')}}" class="uk-flex uk-flex-middle">
        <span uk-icon="icon: calendar; ratio: 1" class="uk-visible@m uk-margin-small-right"></span>
        <span class="uk-vertical-align-middle">Upcoming</span>
    </a>
</li>
```

**Step 8: Run tests to confirm they pass**

```bash
./vendor/bin/phpunit tests/Feature/UpcomingTasksTest.php
```

Expected: All 6 tests PASS.

**Step 9: Run the full test suite**

```bash
./vendor/bin/phpunit
```

Expected: All tests pass (39 existing + 6 new = 45).

**Step 10: Commit**

```bash
git add src/Http/Controllers/UpcomingTasksController.php routes/web.php resources/views/tasks/schedules.blade.php resources/views/partials/sidebar.blade.php tests/Feature/UpcomingTasksTest.php
git commit -m "Add UpcomingTasksController and calendar page scaffold"
```

Do NOT add Co-Authored-By or AI attribution.

---

### Task 2: UpcomingCalendar Vue component

**Files:**
- Create: `resources/assets/js/tasks/components/UpcomingCalendar.vue`
- Delete: `resources/assets/js/tasks/components/ScheduleRow.vue` (stash artifact, not needed)
- Modify: `resources/assets/js/app.js`

**Context:**
- `axios` is available globally as `window.axios` (set up in `resources/assets/js/bootstrap.js`) — no import needed
- `moment` is already imported in `app.js` as `import moment from 'moment'` but is NOT on `window`. In the component, use `import moment from 'moment'`
- Build command: `npm run prod` (runs `gulp --production`)
- The component is mounted in the Blade view as `<upcoming-calendar events-url="..."></upcoming-calendar>`

**Step 1: Delete ScheduleRow.vue**

This file was from the stash but is no longer needed. Delete it:

```bash
rm resources/assets/js/tasks/components/ScheduleRow.vue
```

**Step 2: Create UpcomingCalendar.vue**

Create `resources/assets/js/tasks/components/UpcomingCalendar.vue`:

```vue
<template>
    <div>
        <!-- Controls -->
        <div class="uk-flex uk-flex-between uk-flex-middle uk-margin-bottom">
            <div>
                <button
                    @click="setDays(1)"
                    :class="['uk-button uk-button-small', days === 1 ? 'uk-button-primary' : 'uk-button-default']"
                >
                    1 Day
                </button>
                <button
                    @click="setDays(3)"
                    :class="['uk-button uk-button-small', days === 3 ? 'uk-button-primary' : 'uk-button-default']"
                >
                    3 Days
                </button>
            </div>
            <div>
                <button @click="prev" class="uk-button uk-button-default uk-button-small uk-margin-small-right">
                    <span uk-icon="icon: chevron-left"></span>
                </button>
                <button @click="resetToNow" class="uk-button uk-button-default uk-button-small uk-margin-small-right">
                    Today
                </button>
                <button @click="next" class="uk-button uk-button-default uk-button-small">
                    <span uk-icon="icon: chevron-right"></span>
                </button>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="uk-text-center uk-padding">
            <span uk-spinner="ratio: 2"></span>
        </div>

        <!-- Error -->
        <div v-if="error" class="uk-alert-danger" uk-alert>
            <p>{{ error }}</p>
        </div>

        <!-- Calendar Grid -->
        <div v-if="!loading && !error" class="totem-calendar" :style="gridStyle">
            <!-- Header row: empty time-label cell + one day header per column -->
            <div class="totem-calendar__time-label totem-calendar__time-label--header"></div>
            <div
                v-for="day in dayColumns"
                :key="'header-' + day.key"
                class="totem-calendar__day-header"
            >
                {{ day.label }}
            </div>

            <!-- 24 hour rows -->
            <template v-for="hour in 24">
                <div :key="'label-' + hour" class="totem-calendar__time-label">
                    {{ formatHour(hour - 1) }}
                </div>
                <div
                    v-for="day in dayColumns"
                    :key="day.key + '-' + hour"
                    class="totem-calendar__cell"
                >
                    <div
                        v-for="(event, idx) in eventsForDayAndHour(day.date, hour - 1)"
                        :key="event.task_id + '-' + event.scheduled_at + '-' + idx"
                        class="totem-calendar__event"
                        :title="event.description + ' (' + event.command + ')'"
                    >
                        <span class="totem-calendar__event-time">{{ formatTime(event.scheduled_at) }}</span>
                        <span class="totem-calendar__event-desc">{{ truncate(event.description) }}</span>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>

<script>
    import moment from 'moment';

    export default {
        props: {
            eventsUrl: {
                type: String,
                required: true,
            },
        },

        data() {
            return {
                days: 1,
                currentStart: moment().startOf('hour').toDate(),
                events: [],
                loading: false,
                error: null,
            };
        },

        computed: {
            gridStyle() {
                return {
                    display: 'grid',
                    gridTemplateColumns: '80px repeat(' + this.days + ', 1fr)',
                };
            },

            dayColumns() {
                const cols = [];
                for (let i = 0; i < this.days; i++) {
                    const date = moment(this.currentStart).add(i, 'days');
                    cols.push({
                        key: date.format('YYYY-MM-DD'),
                        label: date.format('ddd, MMM D'),
                        date: date.format('YYYY-MM-DD'),
                    });
                }
                return cols;
            },
        },

        mounted() {
            this.fetchEvents();
        },

        methods: {
            setDays(n) {
                this.days = n;
                this.fetchEvents();
            },

            prev() {
                this.currentStart = moment(this.currentStart).subtract(this.days, 'days').toDate();
                this.fetchEvents();
            },

            next() {
                this.currentStart = moment(this.currentStart).add(this.days, 'days').toDate();
                this.fetchEvents();
            },

            resetToNow() {
                this.currentStart = moment().startOf('hour').toDate();
                this.fetchEvents();
            },

            fetchEvents() {
                this.loading = true;
                this.error = null;
                const start = moment(this.currentStart).toISOString();

                axios.get(this.eventsUrl, { params: { start: start, days: this.days } })
                    .then(response => {
                        this.events = response.data.events;
                    })
                    .catch(() => {
                        this.error = 'Failed to load upcoming events. Please try again.';
                    })
                    .finally(() => {
                        this.loading = false;
                    });
            },

            eventsForDayAndHour(date, hour) {
                return this.events.filter(function (event) {
                    const m = moment(event.scheduled_at);
                    return m.format('YYYY-MM-DD') === date && m.hour() === hour;
                });
            },

            formatHour(hour) {
                return moment().startOf('day').add(hour, 'hours').format('HH:mm');
            },

            formatTime(isoString) {
                return moment(isoString).format('HH:mm');
            },

            truncate(text) {
                return text.length > 20 ? text.substring(0, 20) + '\u2026' : text;
            },
        },
    };
</script>

<style scoped>
    .totem-calendar {
        border: 1px solid #e5e5e5;
        border-radius: 4px;
        overflow: auto;
    }

    .totem-calendar__time-label {
        padding: 4px 8px;
        font-size: 12px;
        color: #999;
        border-right: 1px solid #e5e5e5;
        border-bottom: 1px solid #e5e5e5;
        text-align: right;
        min-height: 60px;
        display: flex;
        align-items: flex-start;
        justify-content: flex-end;
    }

    .totem-calendar__time-label--header {
        min-height: auto;
        background: #f8f8f8;
    }

    .totem-calendar__day-header {
        padding: 8px;
        font-weight: bold;
        text-align: center;
        border-bottom: 2px solid #1e87f0;
        border-right: 1px solid #e5e5e5;
        background: #f8f8f8;
    }

    .totem-calendar__cell {
        min-height: 60px;
        padding: 2px;
        border-bottom: 1px solid #e5e5e5;
        border-right: 1px solid #e5e5e5;
        vertical-align: top;
    }

    .totem-calendar__event {
        background: #1e87f0;
        color: white;
        border-radius: 3px;
        padding: 2px 4px;
        margin-bottom: 2px;
        font-size: 11px;
        overflow: hidden;
        white-space: nowrap;
    }

    .totem-calendar__event-time {
        font-weight: bold;
        margin-right: 4px;
    }

    .totem-calendar__event-desc {
        opacity: 0.9;
    }
</style>
```

**Step 3: Register the component in app.js**

In `resources/assets/js/app.js`, add the import at the top with the other component imports:

```js
import UpcomingCalendar from './tasks/components/UpcomingCalendar'
```

Then add it to the `components` object inside `new Vue({...})`:

```js
'upcoming-calendar': UpcomingCalendar,
```

**Step 4: Build assets**

```bash
npm run prod
```

Expected: Webpack compiles without errors. Output in `public/vendor/totem/js/`.

Then publish the assets so the Totem package's public files are updated:

```bash
php artisan totem:assets
```

**Step 5: Commit**

```bash
git add resources/assets/js/tasks/components/UpcomingCalendar.vue resources/assets/js/app.js public/vendor/totem/
git rm resources/assets/js/tasks/components/ScheduleRow.vue
git commit -m "Add UpcomingCalendar Vue component with 1-day and 3-day views"
```

Do NOT add Co-Authored-By or AI attribution.

---

### Manual Verification Checklist

After both tasks are complete:

1. Visit `/totem/upcoming` — page loads with "Upcoming Schedule" title
2. Sidebar shows "Upcoming" link, active when on this page
3. Calendar grid renders with hourly rows and day column headers
4. Events appear as blue chips in the correct day/hour cell
5. "1 Day" / "3 Day" toggle switches the column count and re-fetches
6. Prev/Next arrows shift the window; Today resets to now
7. No tasks with `is_active = false` appear in any view
8. High-frequency tasks (e.g. `* * * * *`) show all per-minute chips in each hour cell
