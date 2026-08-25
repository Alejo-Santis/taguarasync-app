<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class OperationalAlertNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $alert
     */
    public function __construct(
        private readonly array $alert,
        private readonly bool $sendEmail = false,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database', WebPushChannel::class];

        if ($this->sendEmail || config('taguara.notifications.operational_alerts_email')) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the web push representation of the notification.
     */
    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->alert['title'])
            ->body($this->alert['body'])
            ->data(['url' => $this->alert['href'] ?? '/dashboard']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->alert['title'])
            ->line($this->alert['body'])
            ->action('Revisar en Taguara Sync', url($this->alert['href'] ?? '/dashboard'))
            ->line('Esta alerta fue generada automáticamente por Taguara Sync.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'alert_key' => $this->alert['alert_key'],
            'category' => $this->alert['category'] ?? 'operations',
            'severity' => $this->alert['severity'] ?? 'warning',
            'title' => $this->alert['title'],
            'body' => $this->alert['body'],
            'href' => $this->alert['href'] ?? '/dashboard',
            'meta' => $this->alert['meta'] ?? [],
        ];
    }
}
