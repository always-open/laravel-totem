<?php

namespace Studio\Totem\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class JsonFile implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || $value->getClientOriginalExtension() !== 'json') {
            $fail('The :attribute must be a JSON file.');
        }
    }
}
