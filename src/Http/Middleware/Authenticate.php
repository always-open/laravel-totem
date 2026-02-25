<?php

namespace Studio\Totem\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Studio\Totem\Totem;

class Authenticate
{
    public function handle($request, $next)
    {
        return Totem::check($request) ? $next($request) : abort(403);
    }
}
