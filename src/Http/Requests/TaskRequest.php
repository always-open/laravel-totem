<?php

namespace Studio\Totem\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Studio\Totem\Rules\CronExpression;

class TaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => ['required'],
            'command' => ['required'],
            'expression' => ['nullable', 'required_if:type,expression', new CronExpression],
            'frequencies' => ['required_if:type,frequency', 'array'],
            'notification_email_address' => ['nullable', 'email'],
            'notification_phone_number' => ['nullable', 'digits_between:11,13'],
            'notification_slack_webhook' => ['nullable', 'url'],
        ];
    }

    public function messages(): array
    {
        return [
            'description.required' => 'Task description is required',
            'command.required' => 'Please select a command',
            'expression.required_if' => 'Cron Expression is required if task type is expression',
            'frequencies.required_if' => 'At least one frequency is required',
            'frequencies.array' => 'At least one frequency is required',
            'notification_email_address.email' => 'Email address is not valid',
            'notification_phone_number.digits_between' => 'Phone number should be between 11 and 13 digits including country code',
            'notification_slack_webhook.url' => 'Slack Webhook must be a valid url',
        ];
    }

    public function validationData(): array
    {
        if ($this->input('type') == 'frequency') {
            $this->merge(['expression' => null]);
        }

        return $this->all();
    }
}
