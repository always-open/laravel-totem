<?php

namespace Studio\Totem;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Studio\Totem\Traits\HasParameters;

class Frequency extends TotemModel
{
    use HasParameters;

    protected $table = 'task_frequencies';

    protected $fillable = [
        'id',
        'label',
        'interval',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
