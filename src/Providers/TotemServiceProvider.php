<?php

namespace Studio\Totem\Providers;

use Cron\CronExpression;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use Studio\Totem\Console\Commands\ListSchedule;
use Studio\Totem\Console\Commands\PublishAssets;
use Studio\Totem\Contracts\TaskInterface;
use Studio\Totem\Repositories\EloquentTaskRepository;

class TotemServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerResources();
        $this->defineAssetPublishing();

        Validator::extend('cron_expression', function ($attribute, $value, $parameters, $validator) {
            return CronExpression::isValidExpression($value);
        });

        Validator::extend('json_file', function ($attribute, UploadedFile $value, $validator) {
            return $value->getClientOriginalExtension() == 'json';
        });
    }

    /**
     * Register any services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/totem.php',
            'totem'
        );

        $this->commands([
            ListSchedule::class,
            PublishAssets::class,
        ]);

        $this->app->bindIf('totem.tasks', EloquentTaskRepository::class, true);
        $this->app->alias('totem.tasks', TaskInterface::class);
        $this->app->register(TotemRouteServiceProvider::class);
        $this->app->register(TotemEventServiceProvider::class);
        $this->app->register(ConsoleServiceProvider::class);
    }

    /**
     * Register the Totem resources.
     *
     * @return void
     */
    protected function registerResources()
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'totem');
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../resources/lang', 'totem');
    }

    /**
     * Define the asset publishing configuration.
     *
     * @return void
     */
    public function defineAssetPublishing()
    {
        $this->publishes([
            __DIR__.'/../../public/js' => public_path('vendor/totem/js'),
        ], 'totem-assets');

        $this->publishes([
            __DIR__.'/../../public/css' => public_path('vendor/totem/css'),
        ], 'totem-assets');

        $this->publishes([
            __DIR__.'/../../public/img' => public_path('vendor/totem/img'),
        ], 'totem-assets');

        $this->publishes([
            __DIR__.'/../../resources/views' => resource_path('views/vendor/totem'),
        ], 'totem-views');

        $this->publishes([
            __DIR__.'/../../config' => config_path(),
        ], 'totem-config');
    }
}
