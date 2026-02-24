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
        $request->validate([
            'start' => ['nullable', 'date'],
            'days'  => ['nullable', 'integer', 'in:1,3'],
        ]);

        $start = $request->filled('start')
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
                    if ($next >= $end || $next <= $cursor) {
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
                logger()->warning('Totem: could not parse cron expression for task '.$task->id.': '.$e->getMessage());
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
