<?php

namespace Studio\Totem\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Studio\Totem\Contracts\TaskInterface;
use Studio\Totem\Events\Activated;
use Studio\Totem\Events\Created;
use Studio\Totem\Events\Creating;
use Studio\Totem\Events\Deactivated;
use Studio\Totem\Events\Deleted;
use Studio\Totem\Events\Deleting;
use Studio\Totem\Events\Executed;
use Studio\Totem\Events\Updated;
use Studio\Totem\Events\Updating;
use Studio\Totem\Result;
use Studio\Totem\Task;

class EloquentTaskRepository implements TaskInterface
{
    /**
     * Return task eloquent builder.
     *
     * @return Builder
     */
    public function builder(): Builder
    {
        $result = new Result;

        return (new Task)->select(config('totem.table_prefix', '').'tasks.*')
            ->selectSub(
                $result->getLastRun(),
                'last_ran_at'
            )
            ->selectSub(
                $result->getAverageRunTime(),
                'average_runtime'
            );
    }

    /**
     * Get the cache store instance configured for Totem.
     *
     * @return \Illuminate\Cache\Repository
     */
    private function cache(): \Illuminate\Contracts\Cache\Repository
    {
        return Cache::store(config('totem.cache_store'));
    }

    /**
     * Find a task by id.
     *
     * @param  int|Task  $id
     * @return Task|null
     */
    public function find(Task|int $id)
    {
        if ($id instanceof Task) {
            return $id;
        }

        return $this->cache()->rememberForever('totem.task.'.$id, function () use ($id) {
            return Task::query()->with('frequencies')->find($id);
        });
    }

    /**
     * Find all tasks.
     *
     * @return Collection
     */
    public function findAll(): Collection
    {
        return $this->cache()->rememberForever('totem.tasks.all', function () {
            return Task::query()->with('frequencies')->get();
        });
    }

    /**
     * Find all active tasks.
     *
     * @return Collection
     */
    public function findAllActive(): Collection
    {
        return $this->cache()->rememberForever('totem.tasks.active', function () {
            return $this->findAll()->filter(function ($task) {
                return $task->is_active;
            });
        });
    }

    /**
     * Create a new task.
     *
     * @param  array  $input
     * @return bool|Task
     */
    public function store(array $input): bool|Task
    {
        $task = new Task;

        Creating::dispatch($input);

        $task->fill(Arr::only($input, $task->getFillable()))->save();

        Created::dispatch($task);

        return $task;
    }

    /**
     * Update the given task.
     *
     * @param  array  $input
     * @param  Task  $task
     * @return Task
     */
    public function update(array $input, $task): Task
    {
        $task = $this->find($task);

        Updating::dispatch($input, $task);

        $task->fill(Arr::only($input, $task->getFillable()))->save();

        Updated::dispatch($task);

        return $task;
    }

    /**
     * Delete the given task.
     *
     * @param  int|Task  $id
     * @return bool
     */
    public function destroy(Task|int $id): bool
    {
        $task = $this->find($id);

        if (Deleting::dispatch($task->id) === false) {
            return false;
        }

        $task->delete();

        Deleted::dispatch();

        return true;
    }

    /**
     * Activate the given task.
     *
     * @param  $input
     * @return Task
     */
    public function activate($input): Task
    {
        $task = $this->find($input['task_id']);

        $task->fill(['is_active' => true])->save();

        Activated::dispatch($task);

        return $task;
    }

    /**
     * Deactivate the given task.
     *
     * @param  $id
     * @return Task
     */
    public function deactivate($id): Task
    {
        $task = $this->find($id);

        $task->fill(['is_active' => false])->save();

        Deactivated::dispatch($task);

        return $task;
    }

    /**
     * Execute a given task.
     *
     * @param  int|Task  $id
     * @return Task
     */
    public function execute(Task|int $id): Task
    {
        $task = $this->find($id);
        $start = microtime(true);
        try {
            Artisan::call($task->command, $task->compileParameters());
            $output = Artisan::output();
        } catch (\Exception $e) {
            $output = $e->getMessage();
        }

        Executed::dispatch($task, $start, $output);

        return $task;
    }

    /**
     * Import tasks.
     *
     * @param  $input
     * @return void
     */
    public function import($input): void
    {
        $this->cache()->forget('totem.tasks.all');
        $this->cache()->forget('totem.tasks.active');

        collect(json_decode(Arr::get($input, 'content')))
            ->each(function ($data) {
                $this->cache()->forget('totem.task.'.$data->id);

                $task = $this->find($data->id);

                if (is_null($task)) {
                    $this->store((array) $data);

                    return;
                }

                $this->update((array) $data, $task);
            });
    }
}
