<?php

namespace Studio\Totem\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackAttachment;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Messages\VonageMessage;
use Illuminate\Notifications\Notification;

class TaskCompleted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $output)
    {
    }

    public function via(mixed $notifiable): array
    {
        $channels = [];

        if ($notifiable->notification_email_address) {
            $channels[] = 'mail';
        }
        if ($notifiable->notification_phone_number) {
            $channels[] = 'vonage';
        }
        if ($notifiable->notification_slack_webhook) {
            $channels[] = 'slack';
        }

        return $channels;
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($notifiable->description)
            ->greeting('Hi,')
            ->line("{$notifiable->description} just finished running.")
            ->line($this->output);
    }

    public function toVonage(mixed $notifiable): VonageMessage
    {
        return (new VonageMessage)
            ->content($notifiable->description.' just finished running.');
    }

    public function toSlack(mixed $notifiable): SlackMessage
    {
        return (new SlackMessage)
            ->content(config('app.name'))
            ->attachment(function (SlackAttachment $attachment) use ($notifiable) {
                $attachment
                    ->title('Totem Task')
                    ->content($notifiable->description.' just finished running.');
            });
    }
}
