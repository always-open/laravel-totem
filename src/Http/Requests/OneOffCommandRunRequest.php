<?php

namespace Studio\Totem\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Studio\Totem\OneOffCommandRunner;

/**
 * Pre-checks a one-off run against Totem's allowlist so a refused command returns to the form
 * with an error. OneOffCommandRunner::run() enforces the same predicate itself.
 */
class OneOffCommandRunRequest extends FormRequest
{
    /**
     * Access is decided by Totem's gate on the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $runner = $this->container->make(OneOffCommandRunner::class);

        return [
            'command' => [
                'required',
                'string',
                function (string $attribute, mixed $value, Closure $fail) use ($runner): void {
                    if (! is_string($value) || ! $runner->allows($value)) {
                        $fail('The selected command is not allowed to run from Totem.');
                    }
                },
            ],
            'arguments' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
