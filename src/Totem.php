<?php

namespace Studio\Totem;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Command\Command;
use Throwable;

class Totem
{
    /**
     * The callback that should be used to authenticate Totem users.
     *
     * @var Closure
     */
    public static ?Closure $authUsing = null;

    /**
     * Determine if the given request can access the Totem dashboard.
     *
     * @param  Request|string|null  $request
     * @return bool
     */
    public static function check($request): bool
    {
        return (static::$authUsing ?: function () {
            return app()->environment('local');
        })($request);
    }

    /**
     * Set the callback that should be used to authenticate Totem users.
     *
     * @param  Closure  $callback
     * @return static
     */
    public static function auth(Closure $callback)
    {
        static::$authUsing = $callback;

        return new static();
    }

    /**
     * Return available frequencies.
     *
     * @return array
     */
    public static function frequencies(): array
    {
        return config('totem.frequencies');
    }

    /**
     * Return collection of Artisan commands filtered if needed.
     *
     * @return Collection
     */
    public static function getCommands(): Collection
    {
        $command_filter = config('totem.artisan.command_filter');
        $whitelist = config('totem.artisan.whitelist', true);
        $all_commands = collect(Artisan::all());

        if (! empty($command_filter)) {
            $all_commands = $all_commands->filter(function (Command $command) use ($command_filter, $whitelist) {
                foreach ($command_filter as $filter) {
                    if (fnmatch($filter, $command->getName())) {
                        return $whitelist;
                    }
                }

                return ! $whitelist;
            });
        }

        return $all_commands->sortBy(function (Command $command) {
            $name = $command->getName();
            if (mb_strpos($name, ':') === false) {
                $name = ':'.$name;
            }

            return $name;
        });
    }

    /**
     * @return bool
     */
    public static function isEnabled(): bool
    {
        try {
            $cache = Cache::store(config('totem.cache_store'));

            if ($cache->get('totem.table.'.config('totem.table_prefix', '').'tasks')) {
                return true;
            }

            if (Schema::hasTable(config('totem.table_prefix', '').'tasks')) {
                $cache->forever('totem.table.'.config('totem.table_prefix', '').'tasks', true);

                return true;
            }
        } catch (Throwable $e) {
            return false;
        }

        return false;
    }
}
