<?php

namespace Studio\Totem\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Studio\Totem\Contracts\TaskInterface;
use Studio\Totem\Task;

class ActiveTasksController extends Controller
{
    /**
     * @var TaskInterface
     */
    private TaskInterface $tasks;

    /**
     * @param  TaskInterface  $tasks
     */
    public function __construct(TaskInterface $tasks)
    {
        parent::__construct();

        $this->tasks = $tasks;
    }

    /**
     * Store a newly active task in storage.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $task = $this->tasks->activate($request->all());

        return response()->json($task, 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  Task  $task
     * @return JsonResponse
     */
    public function destroy(Task $task): JsonResponse
    {
        $task = $this->tasks->deactivate($task->id);

        return response()->json($task, 200);
    }
}
