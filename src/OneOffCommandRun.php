<?php

namespace Studio\Totem;

/**
 * One allowlisted Artisan command run once from the "Run once" page, with what it printed and
 * how it ended.
 *
 * `user_email` is the runner's email at run time, so the record reads the same after the user
 * changes or is deleted. Both user columns are null when Totem's gate admitted a request with
 * nobody signed in. A null `exit_code` means the command threw; `error` then holds the exception.
 *
 * `started_at` is stored like every other Totem timestamp: the app timezone's wall-clock time.
 */
class OneOffCommandRun extends TotemModel
{
    protected $table = 'one_off_command_runs';

    protected $fillable = [
        'command',
        'arguments',
        'user_id',
        'user_email',
        'started_at',
        'duration_ms',
        'exit_code',
        'output',
        'error',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'duration_ms' => 'integer',
        'exit_code' => 'integer',
        'user_id' => 'integer',
    ];

    public function succeeded(): bool
    {
        return $this->exit_code === 0;
    }

    public function commandLine(): string
    {
        return trim($this->command.' '.$this->arguments);
    }
}
