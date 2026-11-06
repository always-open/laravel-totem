<?php

namespace Studio\Totem\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Studio\Totem\Http\Requests\OneOffCommandRunRequest;
use Studio\Totem\OneOffCommandRun;
use Studio\Totem\OneOffCommandRunner;

class OneOffCommandRunsController extends Controller
{
    private const PER_PAGE = 50;

    public function index(OneOffCommandRunner $runner): View
    {
        return view('totem::one-off-runs.index', [
            'commands' => $runner->allowedCommands(),
            'runs' => OneOffCommandRun::query()
                ->orderByDesc('started_at')
                ->orderByDesc('id')
                ->paginate(self::PER_PAGE),
        ]);
    }

    public function store(OneOffCommandRunRequest $request, OneOffCommandRunner $runner): RedirectResponse
    {
        $run = $runner->run(
            $request->string('command')->toString(),
            $request->validated('arguments'),
            $request->user(),
        );

        return redirect()->route('totem.one-off-runs.show', $run);
    }

    /**
     * Reads the write connection, so the first view after a run cannot miss its row on replica lag.
     */
    public function show(int $run): View
    {
        return view('totem::one-off-runs.show', [
            'run' => OneOffCommandRun::query()->useWritePdo()->findOrFail($run),
        ]);
    }
}
