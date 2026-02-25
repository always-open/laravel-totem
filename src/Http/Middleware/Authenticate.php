<?php

namespace Studio\Totem\Http\Middleware;

use Studio\Totem\Totem;

class Authenticate
{
    public function handle($request, $next)
    {
        return Totem::check($request) ? $next($request) : abort(403);
    }
}
