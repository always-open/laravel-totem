<?php

namespace Studio\Totem\Tests\Feature;

use Studio\Totem\Tests\TestCase;
use Studio\Totem\Totem;

class TotemCommandsTest extends TestCase
{
    public function test_all_commands_returned_when_no_filter(): void
    {
        config(['totem.artisan.command_filter' => []]);

        $commands = Totem::getCommands();

        $this->assertGreaterThan(1, $commands->count());
    }

    public function test_whitelist_filter_returns_only_matching_commands(): void
    {
        config([
            'totem.artisan.command_filter' => ['totem:*'],
            'totem.artisan.whitelist' => true,
        ]);

        $commands = Totem::getCommands();

        $this->assertNotEmpty($commands);
        foreach ($commands as $command) {
            $this->assertStringStartsWith('totem:', $command->getName());
        }
    }

    public function test_blacklist_filter_excludes_matching_commands(): void
    {
        config([
            'totem.artisan.command_filter' => ['totem:*'],
            'totem.artisan.whitelist' => false,
        ]);

        $commands = Totem::getCommands();

        foreach ($commands as $command) {
            $this->assertStringNotContainsString('totem:', $command->getName());
        }
    }
}
