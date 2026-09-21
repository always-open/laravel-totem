<?php

namespace Studio\Totem;

use Carbon\Carbon;
use Closure;
use Cron\CronExpression;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
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
     */
    public static function frequencies(): array
    {
        return config('totem.frequencies');
    }

    /**
     * Return collection of Artisan commands filtered if needed.
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
     * Resolve the next date a cron expression matches, or null when the parser cannot find one.
     *
     * The parser searches a bounded window of candidate dates and throws a
     * RuntimeException when that search fails. That covers an expression no
     * calendar date satisfies, such as `0 0 31 2 *`, and a nearest-weekday
     * day-of-month (`31W`) evaluated in a month too short to hold it. The
     * parser's own getMultipleRunDates() treats the same exception as "no
     * dates", and surfaces that display a next run render it as "Never".
     * A malformed expression still raises its InvalidArgumentException.
     */
    public static function nextRunDate(string $expression, DateTimeInterface|string $from = 'now', ?string $timezone = null): ?Carbon
    {
        try {
            return Carbon::instance((new CronExpression($expression))->getNextRunDate($from, 0, false, $timezone));
        } catch (RuntimeException $e) {
            if ($e->getMessage() !== 'Impossible CRON expression') {
                logger()->warning('Totem: could not resolve the next run date for ['.$expression.']: '.$e->getMessage());
            }

            return null;
        }
    }

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
