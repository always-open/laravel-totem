<?php

namespace Studio\Totem\Http\Controllers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Studio\Totem\Contracts\TaskInterface;
use Studio\Totem\Http\Requests\PauseRequest;
use Studio\Totem\Http\Requests\TaskRequest;
use Studio\Totem\Pause;
use Studio\Totem\Task;
use Studio\Totem\Totem;

class TasksController extends Controller
{
    /**
     * @var TaskInterface
     */
    private TaskInterface $tasks;

    public function __construct(TaskInterface $tasks)
    {
        parent::__construct();

        $this->tasks = $tasks;
    }

    /**
     * Display a listing of the tasks.
     */
    public function index(): View
    {
        $search = is_string(request('q')) ? request()->string('q')->lower()->toString() : '';

        return view('totem::tasks.index', [
            'tasks' => $this->tasks
                ->builder()
                ->sortableBy([
                    'description',
                    'last_ran_at',
                    'average_runtime',
                ], ['description' => 'asc'])
                ->when($search, function (Builder $query, string $search) {
                    $query->whereRaw('LOWER(description) LIKE ?', ['%'.$search.'%']);
                })
                ->with('frequencies')
                ->paginate(20),
            'pause' => Pause::current(),
            'durations' => PauseRequest::DURATIONS,
        ]);
    }

    /**
     * Show the form for creating a new task.
     */
    public function create(): View
    {
        $commands = Totem::getCommands()->map(function ($command) {
            return ['name' => $command->getName(), 'description' => $command->getDescription()];
        });

        return view('totem::tasks.form', [
            'task' => new Task,
            'commands' => $commands,
            'timezones' => timezone_identifiers_list(),
            'frequencies' => Totem::frequencies(),
        ]);
    }

    /**
     * Store a newly created task in storage.
     */
    public function store(TaskRequest $request): RedirectResponse
    {
        $this->tasks->store($request->all());

        return redirect()
            ->route('totem.tasks.all')
            ->with('success', trans('totem::messages.success.create'));
    }

    /**
     * Display the specified task.
     *
     * @param  Task  $task
     * @return Factory|View
     */
    public function view(Task $task)
    {
        return view('totem::tasks.view', [
            'task' => $task,
        ]);
    }

    /**
     * Show the form for editing the specified task.
     */
    public function edit(Task $task): View
    {
        $commands = Totem::getCommands()->map(function ($command) {
            return ['name' => $command->getName(), 'description' => $command->getDescription()];
        });

        return view('totem::tasks.form', [
            'task' => $task,
            'commands' => $commands,
            'timezones' => timezone_identifiers_list(),
            'frequencies' => Totem::frequencies(),
        ]);
    }

    /**
     * Update the specified task in storage.
     */
    public function update(TaskRequest $request, Task $task): RedirectResponse
    {
        $task = $this->tasks->update($request->all(), $task);

        return redirect()->route('totem.task.view', ['totemTask' => $task])
            ->with('task', $task)
            ->with('success', trans('totem::messages.success.update'));
    }

    /**
     * Remove the specified task from storage.
     */
    public function destroy(Task $task): RedirectResponse
    {
        $this->tasks->destroy($task);

        return redirect()
            ->route('totem.tasks.all')
            ->with('success', trans('totem::messages.success.delete'));
    }
}
