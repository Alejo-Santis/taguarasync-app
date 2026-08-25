<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class BillingAlertNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $alert
     */
    public function __construct(
        private readonly array $alert,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail', WebPushChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('[Taguara Sync] '.$this->alert['title'])
            ->line($this->alert['body'])
            ->action('Ver farmacias', url('/admin/tenants'))
            ->line('Este aviso fue generado automáticamente por Taguara Sync.');
    }

    /**
     * Get the web push representation of the notification.
     */
    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->alert['title'])
            ->body($this->alert['body'])
            ->data(['url' => '/admin/tenants']);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'alert_key' => $this->alert['alert_key'],
            'category' => 'billing',
            'severity' => $this->alert['severity'],
            'title' => $this->alert['title'],
            'body' => $this->alert['body'],
            'href' => '/admin/tenants',
            'meta' => $this->alert['meta'] ?? [],
        ];
    }
}
