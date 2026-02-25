<?php

use Illuminate\Support\Facades\Route;
use Studio\Totem\Http\Controllers\ActiveTasksController;
use Studio\Totem\Http\Controllers\DashboardController;
use Studio\Totem\Http\Controllers\ExecuteTasksController;
use Studio\Totem\Http\Controllers\ExportTasksController;
use Studio\Totem\Http\Controllers\ImportTasksController;
use Studio\Totem\Http\Controllers\TasksController;
use Studio\Totem\Http\Controllers\UpcomingTasksController;

Route::get('/', [DashboardController::class, 'index'])->name('totem.dashboard');

Route::prefix('tasks')->group(function () {
    Route::get('/', [TasksController::class, 'index'])->name('totem.tasks.all');

    Route::get('create', [TasksController::class, 'create'])->name('totem.task.create');
    Route::post('create', [TasksController::class, 'store']);

    Route::get('export', [ExportTasksController::class, 'index'])->name('totem.tasks.export');
    Route::post('import', [ImportTasksController::class, 'index'])->name('totem.tasks.import');

    Route::get('upcoming', [UpcomingTasksController::class, 'index'])->name('totem.upcoming');
    Route::get('upcoming/events', [UpcomingTasksController::class, 'events'])->name('totem.upcoming.events');

    Route::get('{totemTask}', [TasksController::class, 'view'])->name('totem.task.view');

    Route::get('{totemTask}/edit', [TasksController::class, 'edit'])->name('totem.task.edit');
    Route::post('{totemTask}/edit', [TasksController::class, 'update']);

    Route::delete('{totemTask}', [TasksController::class, 'destroy'])->name('totem.task.delete');

    Route::post('status', [ActiveTasksController::class, 'store'])->name('totem.task.activate');
    Route::delete('status/{totemTask}', [ActiveTasksController::class, 'destroy'])->name('totem.task.deactivate');

    Route::get('{totemTask}/execute', [ExecuteTasksController::class, 'index'])->name('totem.task.execute');
});
