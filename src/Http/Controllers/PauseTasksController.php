<?php

namespace Studio\Totem\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Studio\Totem\Http\Requests\PauseRequest;
use Studio\Totem\Pause;

class PauseTasksController extends Controller
{
    /**
     * Pause the scheduled runs of all tasks.
     */
    public function store(PauseRequest $request): RedirectResponse
    {
        Pause::start($request->resumeAt());

        return redirect()
            ->route('totem.tasks.all')
            ->with('success', trans('totem::messages.success.pause'));
    }

    /**
     * Resume the scheduled runs of all tasks.
     */
    public function destroy(): RedirectResponse
    {
        Pause::end();

        return redirect()
            ->route('totem.tasks.all')
            ->with('success', trans('totem::messages.success.resume'));
    }
}
