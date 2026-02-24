<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Studio\Totem\Task;

class ScreenshotSeeder extends Seeder
{
    public function run(): void
    {
        $tasks = [
            ['description' => 'Send daily digest email',  'command' => 'email:digest',      'expression' => '0 8 * * *'],
            ['description' => 'Generate sales report',    'command' => 'report:sales',       'expression' => '0 9 * * 1-5'],
            ['description' => 'Sync user data',           'command' => 'sync:users',         'expression' => '*/30 * * * *'],
            ['description' => 'Clear expired sessions',   'command' => 'session:clear',      'expression' => '0 0 * * *'],
            ['description' => 'Database backup',          'command' => 'backup:run',         'expression' => '0 2 * * *'],
            ['description' => 'Send weekly summary',      'command' => 'report:weekly',      'expression' => '0 10 * * 1'],
            ['description' => 'Process payment queue',    'command' => 'payments:process',   'expression' => '*/15 * * * *'],
            ['description' => 'Prune old logs',           'command' => 'logs:prune',         'expression' => '0 3 * * *'],
            ['description' => 'Health check ping',        'command' => 'health:check',       'expression' => '*/5 * * * *'],
            ['description' => 'Archive completed orders', 'command' => 'orders:archive',     'expression' => '0 1 * * *'],
        ];

        foreach ($tasks as $attributes) {
            Task::create(array_merge($attributes, ['is_active' => true]));
        }
    }
}
