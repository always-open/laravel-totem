<?php

namespace Studio\Totem\Tests\Fixtures;

use Illuminate\Console\Command;

/**
 * A test-only Artisan command that counts its own handle() calls, so a test reads "ran once" or
 * "never ran" from the instance the console kernel actually invoked.
 */
abstract class CountingCommand extends Command
{
    public int $runs = 0;
}
