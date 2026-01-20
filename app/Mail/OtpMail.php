<?php

namespace App\Mail;

use App\Enums\OtpType;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public OtpType $type,
        public ?string $userName = null
    ) {}

    public function envelope(): Envelope
    {
        $subject = match ($this->type) {
            OtpType::EMAIL_VERIFICATION => 'Verify your email - CueSports Africa',
            OtpType::PASSWORD_RESET => 'Reset your password - CueSports Africa',
            OtpType::LOGIN => 'Login verification - CueSports Africa',
            OtpType::PHONE_VERIFICATION => 'Verify your phone - CueSports Africa',
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.otp');
    }
}
