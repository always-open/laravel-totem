<?php

namespace Studio\Totem\Tests\Feature\Rules;

use Illuminate\Http\UploadedFile;
use Studio\Totem\Rules\JsonFile;
use Studio\Totem\Tests\TestCase;

class JsonFileRuleTest extends TestCase
{
    public function test_json_file_passes(): void
    {
        $rule = new JsonFile;
        $file = UploadedFile::fake()->create('tasks.json', 100, 'application/json');
        $failed = false;

        $rule->validate('tasks', $file, function () use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed);
    }

    public function test_non_json_file_fails(): void
    {
        $rule = new JsonFile;
        $file = UploadedFile::fake()->create('tasks.csv', 100, 'text/csv');
        $message = null;

        $rule->validate('tasks', $file, function (string $msg) use (&$message) {
            $message = $msg;
        });

        $this->assertNotNull($message);
    }
}
