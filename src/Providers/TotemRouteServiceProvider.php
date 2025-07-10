<?php

namespace Studio\Totem\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider;
use Illuminate\Support\Facades\Route;
use Studio\Totem\Task;

class TotemRouteServiceProvider extends RouteServiceProvider
{
    /**
     * This namespace is applied to your controller routes.
     *
     * In addition, it is set as the URL generator's root namespace.
     *
     * @var string
     */
    protected $namespace = 'Studio\Totem\Http\Controllers';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        Route::bind('totemTask', function ($value) {
            return cache()->rememberForever('totem.task.'.$value, function () use ($value) {
                return Task::find($value) ?? abort(404);
            });
        });
    }

    /**
     * Define the routes for the application.
     *
     * @return void
     */
    public function map()
    {
        $this->mapApiRoutes();

        $this->mapWebRoutes();
    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     *
     * @return void
     */
    protected function mapWebRoutes()
    {
        Route::prefix(config('totem.web.route_prefix', 'totem'))
            ->middleware(config('totem.web.middleware'))
            ->namespace($this->namespace)
            ->group(__DIR__.'/../../routes/web.php');
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     *
     * @return void
     */
    protected function mapApiRoutes()
    {
        Route::prefix('api')
            ->middleware(config('totem.web.middleware'))
            ->namespace($this->namespace)
            ->group(__DIR__.'/../../routes/api.php');
    }
}
