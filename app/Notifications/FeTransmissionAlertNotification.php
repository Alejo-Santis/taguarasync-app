<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FeTransmissionAlertNotification extends Notification
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
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($this->sendEmail || config('taguara.notifications.fe_alerts_email')) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->alert['title'])
            ->line($this->alert['body'])
            ->action('Revisar facturación electrónica', url($this->alert['href'] ?? '/fe/submissions'))
            ->line('Esta alerta fue generada automáticamente por Taguara Sync.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'alert_key' => $this->alert['alert_key'],
            'category' => $this->alert['category'] ?? 'billing',
            'severity' => $this->alert['severity'] ?? 'warning',
            'title' => $this->alert['title'],
            'body' => $this->alert['body'],
            'href' => $this->alert['href'] ?? '/fe/submissions',
            'meta' => $this->alert['meta'] ?? [],
        ];
    }
}
