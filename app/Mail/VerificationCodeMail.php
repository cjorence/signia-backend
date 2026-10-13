<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public string $name = 'there'
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your Signia Verification Code: {$this->code}",
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: "
            <div style=\"font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; max-width: 560px; margin: 0 auto; padding: 32px 24px; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 16px;\">
                <div style=\"text-align: center; margin-bottom: 24px;\">
                    <h2 style=\"color: #10b981; margin: 0; font-size: 28px; font-weight: 800; letter-spacing: 1px;\">SIGNIA</h2>
                    <p style=\"color: #6b7280; font-size: 14px; margin-top: 4px;\">Filipino Sign Language Learning Platform</p>
                </div>
                
                <h3 style=\"color: #1f2937; font-size: 18px; margin-bottom: 12px;\">Hello {$this->name},</h3>
                <p style=\"color: #4b5563; font-size: 15px; line-height: 1.6; margin-bottom: 24px;\">
                    Thank you for signing up with Signia! To complete your registration and verify your email address, please enter the following 6-digit verification code:
                </p>
                
                <div style=\"background: #ecfdf5; border: 2px dashed #10b981; border-radius: 12px; padding: 20px; text-align: center; margin-bottom: 24px;\">
                    <span style=\"font-size: 36px; font-weight: 800; letter-spacing: 8px; color: #047857; font-family: monospace;\">{$this->code}</span>
                </div>
                
                <p style=\"color: #6b7280; font-size: 13px; line-height: 1.5; margin-bottom: 24px;\">
                    This code will expire in <strong>15 minutes</strong>. If you did not request this registration, you can safely ignore this email.
                </p>
                
                <hr style=\"border: none; border-top: 1px solid #f3f4f6; margin: 24px 0;\" />
                
                <p style=\"color: #9ca3af; font-size: 12px; text-align: center; margin: 0;\">
                    © " . date('Y') . " Signia. All rights reserved.
                </p>
            </div>
            ",
        );
    }
}
