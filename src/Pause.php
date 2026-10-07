<?php

namespace Studio\Totem;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;

/**
 * A period during which every Totem task's scheduled runs are skipped.
 *
 * Tasks keep their own is_active flags; a pause sits on top of them. A pause
 * with a resume_at ends on its own once that time passes, so no job is needed
 * to lift it. Ended pauses stay in the table as history.
 */
class Pause extends TotemModel
{
    protected $table = 'schedule_pauses';

    protected $fillable = [
        'paused_at',
        'resume_at',
        'resumed_at',
    ];

    protected $casts = [
        'paused_at' => 'datetime',
        'resume_at' => 'datetime',
        'resumed_at' => 'datetime',
    ];

    /**
     * Limit the query to pauses in effect right now.
     */
    public function scopeActive(Builder $query): Builder
    {
        $now = Carbon::now();

        return $query->whereNull('resumed_at')
            ->where(function (Builder $query) use ($now) {
                $query->whereNull('resume_at')->orWhere('resume_at', '>', $now);
            });
    }

    /**
     * The pause in effect right now, or null when tasks run normally.
     *
     * Returns null when the table does not exist yet, so the scheduler keeps
     * working before the migration has run. Any other database error is
     * thrown rather than read as "not paused".
     */
    public static function current(): ?self
    {
        try {
            return static::query()->active()->latest('id')->first();
        } catch (QueryException $e) {
            $model = new static;

            if (! $model->getConnection()->getSchemaBuilder()->hasTable($model->getTable())) {
                return null;
            }

            throw $e;
        }
    }

    /**
     * Pause all tasks until the given time, or until resumed when null.
     *
     * Calling this during a pause replaces that pause's resume time. The
     * time is converted to the app timezone first, because Eloquent stores a
     * date's wall-clock time without converting it.
     */
    public static function start(?Carbon $until = null): self
    {
        $pause = static::current() ?? new static(['paused_at' => Carbon::now()]);

        $pause->fill(['resume_at' => $until?->copy()->setTimezone(config('app.timezone'))])->save();

        return $pause;
    }

    /**
     * End every pause in effect. Returns false when tasks were not paused.
     *
     * Two pauses started at the same moment can both be active, so all of
     * them are ended rather than only the newest.
     */
    public static function end(): bool
    {
        return static::query()->active()->update(['resumed_at' => Carbon::now()]) > 0;
    }
}
