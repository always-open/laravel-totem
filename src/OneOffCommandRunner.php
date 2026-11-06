<?php

namespace Studio\Totem;

use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Console\Kernel;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

/**
 * Runs one Totem-allowlisted Artisan command once and records the run.
 *
 * The allowlist is Totem's own (`Totem::getCommands()`, driven by `totem.artisan.command_filter`
 * and `totem.artisan.whitelist`), so a command is runnable here exactly when Totem would offer it
 * for a task. The arguments string is parsed by Symfony's console input rules, not the task
 * parameter compiler, so a valueless option that accepts a value reaches the command as `null`
 * rather than `true` / `'1'`.
 *
 * The run is non-interactive: no terminal sits behind a web request, so `confirm()` and `ask()`
 * take their defaults. `--no-interaction` goes directly after the command name, ahead of any
 * `--` the operator's arguments carry, because Symfony reads it only before that separator.
 */
class OneOffCommandRunner
{
    public function __construct(private readonly Kernel $kernel)
    {
    }

    /**
     * @return list<string>
     */
    public function allowedCommands(): array
    {
        return Totem::getCommands()
            ->map(fn (Command $command): string => (string) $command->getName())
            ->values()
            ->all();
    }

    public function allows(string $command): bool
    {
        return in_array($command, $this->allowedCommands(), true);
    }

    /**
     * Runs the command inside the current process and writes exactly one run row once it returns
     * or throws. A command outside the allowlist is refused before anything runs.
     *
     * @throws InvalidArgumentException when the command is not allowlisted
     */
    public function run(string $command, ?string $arguments, ?Authenticatable $user): OneOffCommandRun
    {
        if (! $this->allows($command)) {
            throw new InvalidArgumentException("The command \"{$command}\" is not allowed to run from Totem.");
        }

        $buffer = new BufferedOutput;
        $startedAt = Carbon::now();
        $start = hrtime(true);
        $exitCode = null;
        $error = null;

        try {
            $exitCode = $this->kernel->call(trim("{$command} --no-interaction {$arguments}"), [], $buffer);
        } catch (Throwable $exception) {
            $error = $exception::class.': '.$exception->getMessage();
        }

        return OneOffCommandRun::query()->create([
            'command' => $command,
            'arguments' => $arguments === null ? null : $this->storableText($arguments),
            'user_id' => $user?->getAuthIdentifier(),
            'user_email' => $user === null ? null : data_get($user, 'email'),
            'started_at' => $startedAt,
            'duration_ms' => intdiv(hrtime(true) - $start, 1_000_000),
            'exit_code' => $exitCode,
            'output' => $this->storableText($buffer->fetch()),
            'error' => $error === null ? null : $this->storableText($error),
        ]);
    }

    /**
     * Postgres `text` rejects invalid UTF-8 and cannot hold a NUL byte, and MySQL's utf8mb4
     * rejects invalid UTF-8 in strict mode, so the arguments, output and error are stored without
     * NUL bytes and with invalid sequences replaced. The command itself runs with the arguments
     * as submitted.
     */
    private function storableText(string $text): string
    {
        return mb_scrub(str_replace("\0", '', $text), 'UTF-8');
    }
}
