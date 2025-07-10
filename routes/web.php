<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
*/

Route::get('/', 'DashboardController@index')->name('totem.dashboard');

Route::group(['prefix' => 'tasks'], function () {
    Route::get('/', 'TasksController@index')->name('totem.tasks.all');

    Route::get('create', 'TasksController@create')->name('totem.task.create');
    Route::post('create', 'TasksController@store');

    Route::get('export', 'ExportTasksController@index')->name('totem.tasks.export');
    Route::post('import', 'ImportTasksController@index')->name('totem.tasks.import');

    Route::get('{totemTask}', 'TasksController@view')->name('totem.task.view');

    Route::get('{totemTask}/edit', 'TasksController@edit')->name('totem.task.edit');
    Route::post('{totemTask}/edit', 'TasksController@update');

    Route::delete('{totemTask}', 'TasksController@destroy')->name('totem.task.delete');

    Route::post('status', 'ActiveTasksController@store')->name('totem.task.activate');
    Route::delete('status/{totemTask}', 'ActiveTasksController@destroy')->name('totem.task.deactivate');

    Route::get('{totemTask}/execute', 'ExecuteTasksController@index')->name('totem.task.execute');
});
