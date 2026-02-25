<?php

namespace Studio\Totem\Traits;

use Closure;
use Illuminate\Console\Scheduling\ManagesFrequencies;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Studio\Totem\Frequency;
use Studio\Totem\Task;

trait HasFrequencies
{
    use ManagesFrequencies;

    /**
     * The array of filter callbacks.
     *
     * @var array
     */
    protected array $filters = [];

    /**
     * The array of reject callbacks.
     *
     * @var array
     */
    protected array $rejects = [];

    public static function bootHasFrequencies(): void
    {
        static::deleting(function ($model) {
            $model->beforeDelete();
        });
    }

    /**
     * When task is updated or created, we grab the input. If the type is set to frequency in input we try to either
     * update or create the frequencies included in input else delete the frequency. If the type is not frequency and
     * the task in question has frequencies saved in databased, delete them all.
     */
    public function afterSave(array $input = []): void
    {
        if (isset($input['type'])) {
            if ($input['type'] == 'frequency') {
                foreach ($this->frequencies as $frequency) {
                    if (! in_array($frequency->interval, collect($input['frequencies'])->pluck('interval')->toArray())) {
                        $frequency->delete();
                    }
                }

                foreach ($input['frequencies'] as $_frequency) {
                    $frequency = $this->frequencies()->updateOrCreate(Arr::only($_frequency, ['task_id', 'label', 'interval']));
                    $frequency->afterSave($input);
                }
            } else {
                $this->frequencies->each(function ($frequency) {
                    $frequency->delete();
                });
            }
        }
    }

    /**
     * Task Deleted.
     */
    public function beforeDelete()
    {
        $this->frequencies->each(function ($frequency) {
            $frequency->delete();
        });

        $this->results()->delete();
    }

    /**
     * Frequencies Relation.
     */
    public function frequencies(): HasMany
    {
        return $this->hasMany(Frequency::class, 'task_id', 'id')->with('parameters');
    }

    /**
     * Generate a cron expression from frequencies.
     */
    public function getCronExpression(): string
    {
        if (! $this->expression) {
            $this->expression = '* * * * *';

            foreach ($this->frequencies as $frequency) {
                call_user_func_array([$this, $frequency->interval], $frequency->parameters->pluck('value')->toArray());
            }

            $expression = $this->expression;

            $this->expression = null;

            return $expression;
        }

        return $this->expression;
    }

    /**
     * Determine if the filters pass for the event.
     */
    public function filtersPass(Application $app): bool
    {
        foreach ($this->filters as $callback) {
            if (! $app->call($callback)) {
                return false;
            }
        }

        foreach ($this->rejects as $callback) {
            if ($app->call($callback)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Register a callback to further filter the schedule.
     */
    public function when(Closure $callback): static
    {
        $this->filters[] = $callback;

        return $this;
    }

    /**
     * Schedule the event to run between start and end time.
     */
    public function between($startTime, $endTime): static
    {
        return $this->when($this->inTimeInterval($startTime, $endTime));
    }
}
