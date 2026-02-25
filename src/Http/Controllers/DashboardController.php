<?php

namespace Studio\Totem\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class DashboardController extends Controller
{
    /**
     * Single page application catch-all route.
     */
    public function index(): RedirectResponse
    {
        return redirect()->route('totem.tasks.all');
    }
}
