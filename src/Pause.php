<?php

namespace Studio\Totem;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

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
     * Limit the query to pauses in effect at the given time.
     */
    public function scopeActive(Builder $query, ?Carbon $at = null): Builder
    {
        $at ??= Carbon::now();

        return $query->whereNull('resumed_at')
            ->where('paused_at', '<=', $at)
            ->where(function (Builder $query) use ($at) {
                $query->whereNull('resume_at')->orWhere('resume_at', '>', $at);
            });
    }

    /**
     * The pause in effect right now, or null when tasks run normally.
     *
     * Returns null when the table cannot be read, such as before the
     * migration has run, so the scheduler keeps working.
     */
    public static function current(): ?self
    {
        try {
            return static::query()->active()->latest('id')->first();
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Pause all tasks until the given time, or until resumed when null.
     *
     * Calling this during a pause replaces that pause's resume time.
     */
    public static function start(?Carbon $until = null): self
    {
        $pause = static::current() ?? new static(['paused_at' => Carbon::now()]);

        $pause->fill(['resume_at' => $until])->save();

        return $pause;
    }

    /**
     * End the current pause. Returns false when tasks were not paused.
     */
    public static function end(): bool
    {
        $pause = static::current();

        if (is_null($pause)) {
            return false;
        }

        $pause->fill(['resumed_at' => Carbon::now()])->save();

        return true;
    }

    /**
     * Determine whether this pause applies at the given time.
     */
    public function isInEffect(?Carbon $at = null): bool
    {
        $at ??= Carbon::now();

        return is_null($this->resumed_at)
            && $this->paused_at->lte($at)
            && (is_null($this->resume_at) || $this->resume_at->gt($at));
    }
}
