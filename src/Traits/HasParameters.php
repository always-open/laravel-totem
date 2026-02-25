<?php

namespace Studio\Totem\Traits;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Studio\Totem\Parameter;

trait HasParameters
{
    /**
     * Boot HasParameters Trait.
     */
    public static function bootHasParameters(): void
    {
        static::deleting(function ($model) {
            $model->beforeDelete();
        });
    }

    public function afterSave(array $input = []): void
    {
        $frequency = collect($input['frequencies'] ?? [])->filter(function ($frequency) {
            return $frequency['interval'] == $this->interval;
        })->first();

        if (isset($frequency['parameters'])) {
            foreach ($frequency['parameters'] as $parameter) {
                $this->parameters()->updateOrCreate(Arr::only($parameter, 'name'), $parameter);
            }
        }
    }

    public function beforeDelete()
    {
        $this->parameters()->delete();
    }

    /**
     * @return HasMany
     */
    public function parameters(): HasMany
    {
        return $this->hasMany(Parameter::class);
    }
}
