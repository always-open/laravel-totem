<?php

namespace Studio\Totem\Console\Commands;

use Illuminate\Console\Command;

class PublishAssets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'totem:assets';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Re-publish Totem assets';

    public function handle()
    {
        $this->call('vendor:publish', [
            '--tag' => 'totem-assets',
            '--force' => true,
        ]);
    }
}
