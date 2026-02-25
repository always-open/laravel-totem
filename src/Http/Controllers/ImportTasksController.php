<?php

namespace Studio\Totem\Http\Controllers;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Studio\Totem\Contracts\TaskInterface;
use Studio\Totem\Http\Requests\ImportRequest;

class ImportTasksController extends Controller
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
     * Import tasks from a json file.
     *
     * @param  ImportRequest  $request
     *
     * @throws FileNotFoundException
     */
    public function index(ImportRequest $request)
    {
        $this->tasks->import($request->validated());
    }
}
