<?php

namespace Studio\Totem\Rules;

use Closure;
use Cron\CronExpression as CronParser;
use Illuminate\Contracts\Validation\ValidationRule;

class CronExpression implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! CronParser::isValidExpression($value)) {
            $fail('This is not a valid cron expression.');
        }
    }
}
