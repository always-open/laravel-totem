<?php

namespace Studio\Totem\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class PauseRequest extends FormRequest
{
    /**
     * Preset pause lengths offered on the dashboard, in minutes.
     */
    public const DURATIONS = [
        15 => '15 minutes',
        60 => '1 hour',
        240 => '4 hours',
        1440 => '24 hours',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'duration' => ['nullable', 'integer', 'in:'.implode(',', array_keys(self::DURATIONS)), 'prohibits:until'],
            'until' => ['nullable', 'date', 'after:now'],
        ];
    }

    public function messages(): array
    {
        return [
            'duration.prohibits' => 'Choose either a duration or a resume time, not both.',
            'until.after' => 'The resume time must be in the future.',
        ];
    }

    /**
     * When the pause should end on its own, or null to pause until resumed.
     */
    public function resumeAt(): ?Carbon
    {
        if ($this->filled('duration')) {
            return Carbon::now()->addMinutes((int) $this->input('duration'));
        }

        if ($this->filled('until')) {
            return Carbon::parse($this->input('until'));
        }

        return null;
    }
}
