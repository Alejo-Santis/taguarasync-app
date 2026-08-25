<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ServerErrorOccurred extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $exceptionClass,
        public readonly string $exceptionMessage,
        public readonly string $file,
        public readonly int $line,
        public readonly string $url,
        public readonly string $occurredAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Error de servidor en {$this->exceptionClass}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.server-error',
        );
    }
}
