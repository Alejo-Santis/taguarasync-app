<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

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
        return ['database', 'mail'];
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
