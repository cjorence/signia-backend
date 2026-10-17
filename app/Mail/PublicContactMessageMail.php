<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PublicContactMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $senderEmail,
        public string $senderName,
        public string $subjectLine,
        public string $userMessage
    ) {}

    public function envelope(): Envelope
    {
        $subject = !empty($this->subjectLine) ? $this->subjectLine : 'General Inquiry';
        $sender = !empty($this->senderName) ? $this->senderName : 'Website Visitor';

        return new Envelope(
            subject: "[Signia Contact] {$subject}",
            replyTo: [
                new Address($this->senderEmail, $sender),
            ]
        );
    }

    public function content(): Content
    {
        $sender = htmlspecialchars(!empty($this->senderName) ? $this->senderName : 'Website Visitor', ENT_QUOTES, 'UTF-8');
        $email = htmlspecialchars($this->senderEmail, ENT_QUOTES, 'UTF-8');
        $subject = htmlspecialchars(!empty($this->subjectLine) ? $this->subjectLine : 'General Inquiry', ENT_QUOTES, 'UTF-8');
        $messageBody = nl2br(htmlspecialchars($this->userMessage, ENT_QUOTES, 'UTF-8'));
        $sentAt = now()->timezone('Asia/Manila')->format('M d, Y h:i A');
        $year = date('Y');

        return new Content(
            htmlString: "
            <div style=\"font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 32px 24px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 20px;\">
                <!-- Header -->
                <div style=\"margin-bottom: 24px; border-bottom: 2px solid #10b981; padding-bottom: 16px;\">
                    <span style=\"display: inline-block; background: #ecfdf5; color: #047857; font-size: 11px; font-weight: 800; text-transform: uppercase; padding: 4px 10px; border-radius: 8px; margin-bottom: 8px;\">
                        Landing Page Contact Inquiry
                    </span>
                    <h2 style=\"color: #0f172a; margin: 0; font-size: 20px; font-weight: 800;\">{$subject}</h2>
                    <p style=\"color: #64748b; font-size: 13px; margin: 6px 0 0 0;\">Received from public website footer on {$sentAt}</p>
                </div>

                <!-- Sender Meta -->
                <div style=\"background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 16px; margin-bottom: 24px;\">
                    <table style=\"width: 100%; border-collapse: collapse; font-size: 13px;\">
                        <tr>
                            <td style=\"padding: 4px 0; color: #64748b;\">From:</td>
                            <td style=\"padding: 4px 0; font-weight: 700; color: #0f172a; text-align: right;\">{$sender}</td>
                        </tr>
                        <tr>
                            <td style=\"padding: 4px 0; color: #64748b;\">Email:</td>
                            <td style=\"padding: 4px 0; font-weight: 700; color: #0f172a; text-align: right;\"><a href=\"mailto:{$email}\" style=\"color: #059669; text-decoration: none;\">{$email}</a></td>
                        </tr>
                    </table>
                </div>

                <!-- Message Body -->
                <div style=\"margin-bottom: 28px;\">
                    <h4 style=\"font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; margin: 0 0 10px 0;\">Message Details:</h4>
                    <div style=\"background: #ffffff; border: 1px solid #cbd5e1; border-radius: 14px; padding: 20px; font-size: 14px; line-height: 1.6; color: #1e293b;\">
                        {$messageBody}
                    </div>
                </div>

                <!-- Reply Hint -->
                <div style=\"background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 14px; font-size: 12px; color: #166534; margin-bottom: 24px;\">
                    💡 <strong>Tip:</strong> You can directly hit <strong>\"Reply\"</strong> in your email client to respond to <strong>{$sender} ({$email})</strong>.
                </div>

                <!-- Footer -->
                <div style=\"text-align: center; border-top: 1px solid #f1f5f9; padding-top: 20px; font-size: 12px; color: #94a3b8;\">
                    <p style=\"margin: 0;\">Signia Filipino Sign Language Learning Platform</p>
                    <p style=\"margin: 4px 0 0 0;\">© {$year} Signia. All rights reserved.</p>
                </div>
            </div>
            "
        );
    }
}
