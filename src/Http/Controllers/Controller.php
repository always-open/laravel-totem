<?php

namespace Studio\Totem\Http\Controllers;

use Illuminate\Routing\Controller as BaseController;
use Studio\Totem\Http\Middleware\Authenticate;

class Controller extends BaseController
{
    public function __construct()
    {
        $this->middleware(Authenticate::class);
    }
}
