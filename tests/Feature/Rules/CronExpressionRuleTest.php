<?php

namespace Studio\Totem\Tests\Feature\Rules;

use Studio\Totem\Rules\CronExpression;
use Studio\Totem\Tests\TestCase;

class CronExpressionRuleTest extends TestCase
{
    public function test_valid_cron_expression_passes(): void
    {
        $rule = new CronExpression;
        $failed = false;

        $rule->validate('expression', '* * * * *', function () use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed);
    }

    public function test_invalid_cron_expression_fails(): void
    {
        $rule = new CronExpression;
        $message = null;

        $rule->validate('expression', 'not-a-cron', function (string $msg) use (&$message) {
            $message = $msg;
        });

        $this->assertNotNull($message);
        $this->assertStringContainsString('valid cron expression', $message);
    }

    public function test_five_part_expression_passes(): void
    {
        $rule = new CronExpression;
        $failed = false;

        $rule->validate('expression', '0 9 * * 1-5', function () use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed);
    }
}
