<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupportMessageConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $category,
        public string $subjectLine,
        public string $userMessage
    ) {}

    public function envelope(): Envelope
    {
        $categoryLabels = [
            'suggestion' => 'Suggestion',
            'bug_report' => 'Bug Report',
            'general_inquiry' => 'Inquiry',
            'billing_issue' => 'Billing',
        ];

        $catLabel = $categoryLabels[$this->category] ?? 'Support';
        $supportEmail = config('mail.support_address', 'signiafsl@gmail.com');

        return new Envelope(
            subject: "[Signia {$catLabel}] {$this->subjectLine}",
            replyTo: [
                new Address($supportEmail, 'Signia Support Desk'),
            ]
        );
    }

    public function content(): Content
    {
        $categoryLabels = [
            'suggestion' => '💡 Suggestion / Idea',
            'bug_report' => '🐛 Bug / Problem Report',
            'general_inquiry' => '💬 General Question',
            'billing_issue' => '💳 Billing & Purchases',
        ];

        $catDisplay = htmlspecialchars($categoryLabels[$this->category] ?? ucfirst($this->category), ENT_QUOTES, 'UTF-8');
        $userName = htmlspecialchars($this->user->name ?? 'Learner', ENT_QUOTES, 'UTF-8');
        $subject = htmlspecialchars($this->subjectLine, ENT_QUOTES, 'UTF-8');
        $messageBody = nl2br(htmlspecialchars($this->userMessage, ENT_QUOTES, 'UTF-8'));
        $sentAt = now()->timezone('Asia/Manila')->format('M d, Y h:i A');
        $year = date('Y');

        return new Content(
            htmlString: "
            <div style=\"font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 32px 24px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 20px;\">
                
                <!-- Header -->
                <div style=\"margin-bottom: 24px; border-bottom: 2px solid #10b981; padding-bottom: 16px;\">
                    <span style=\"display: inline-block; background: #ecfdf5; color: #047857; font-size: 11px; font-weight: 800; text-transform: uppercase; padding: 4px 10px; border-radius: 8px; margin-bottom: 8px;\">
                        Message Confirmation
                    </span>
                    <h2 style=\"color: #0f172a; margin: 0; font-size: 20px; font-weight: 800;\">We received your message!</h2>
                    <p style=\"color: #64748b; font-size: 13px; margin: 6px 0 0 0;\">Received on {$sentAt}</p>
                </div>

                <!-- Greeting -->
                <p style=\"font-size: 15px; color: #1e293b; margin: 0 0 16px 0; line-height: 1.5;\">
                    Hi <strong>{$userName}</strong>,
                </p>
                <p style=\"font-size: 14px; color: #475569; margin: 0 0 20px 0; line-height: 1.6;\">
                    Thank you for reaching out to us! This is an automated confirmation to let you know our team has received your <strong>{$catDisplay}</strong> regarding <em>\"{$subject}\"</em>.
                </p>

                <!-- Copy of user's message -->
                <div style=\"margin-bottom: 24px;\">
                    <h4 style=\"font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; margin: 0 0 8px 0;\">
                        Copy of your message for your records:
                    </h4>
                    <div style=\"background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; font-size: 13px; line-height: 1.6; color: #334155;\">
                        {$messageBody}
                    </div>
                </div>

                <!-- Next Steps Info Card -->
                <div style=\"background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 16px; margin-bottom: 24px;\">
                    <h5 style=\"margin: 0 0 4px 0; font-size: 13px; font-weight: 700; color: #166534;\">What happens next?</h5>
                    <p style=\"margin: 0; font-size: 12px; color: #166534; line-height: 1.5;\">
                        Our community and technical team will review your message. We usually respond within <strong>24 to 48 hours</strong>.
                    </p>
                    <p style=\"margin: 8px 0 0 0; font-size: 12px; color: #166534; line-height: 1.5;\">
                        💬 If you need to add more details or attach screenshots, simply <strong>reply directly to this email</strong> and it will route straight to our support team.
                    </p>
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
