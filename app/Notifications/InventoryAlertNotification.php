<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InventoryAlertNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
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

        if ($this->sendEmail || config('taguara.notifications.inventory_alerts_email')) {
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
            ->action('Revisar inventario', url($this->alert['href'] ?? '/inventory'))
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
            'category' => $this->alert['category'] ?? 'inventory',
            'severity' => $this->alert['severity'] ?? 'info',
            'title' => $this->alert['title'],
            'body' => $this->alert['body'],
            'href' => $this->alert['href'] ?? '/dashboard',
            'meta' => $this->alert['meta'] ?? [],
        ];
    }
}
