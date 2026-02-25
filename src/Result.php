<?php

namespace Studio\Totem;

use Database\Factories\TotemResultFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class Result extends TotemModel
{
    use HasFactory;

    protected $table = 'task_results';

    protected $fillable = [
        'duration',
        'result',
    ];

    protected $casts = [
        'ran_at' => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function getLastRun(): Builder
    {
        return $this->select('ran_at')
            ->whereColumn('task_id', config('totem.table_prefix', '').'tasks.id')
            ->latest()
            ->limit(1)
            ->getQuery();
    }

    public function getAverageRunTime(): Builder
    {
        return $this->select(DB::raw('avg(duration)'))
            ->whereColumn('task_id', config('totem.table_prefix', '').'tasks.id')
            ->getQuery();
    }

    protected static function newFactory(): TotemResultFactory
    {
        return TotemResultFactory::new();
    }
}
