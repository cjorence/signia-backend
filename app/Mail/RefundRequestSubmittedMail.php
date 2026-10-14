<?php

namespace App\Mail;

use App\Models\RefundRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RefundRequestSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public RefundRequest $refundRequest
    ) {}

    public function envelope(): Envelope
    {
        $orderId = $this->refundRequest->purchase_id;
        return new Envelope(
            subject: "Signia: Refund Request Received for Order #{$orderId}",
        );
    }

    public function content(): Content
    {
        $userName = htmlspecialchars($this->refundRequest->user->name ?? 'Student', ENT_QUOTES, 'UTF-8');
        $orderId = $this->refundRequest->purchase_id;
        $amount = number_format((float) $this->refundRequest->amount, 2);
        $currency = htmlspecialchars($this->refundRequest->currency, ENT_QUOTES, 'UTF-8');
        $reason = htmlspecialchars(ucwords(str_replace('_', ' ', $this->refundRequest->reason)), ENT_QUOTES, 'UTF-8');
        $explanation = htmlspecialchars($this->refundRequest->explanation ?? 'None provided', ENT_QUOTES, 'UTF-8');
        $year = date('Y');

        return new Content(
            htmlString: "
            <div style=\"font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; max-width: 580px; margin: 0 auto; padding: 32px 24px; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 16px;\">
                <div style=\"text-align: center; margin-bottom: 24px;\">
                    <h2 style=\"color: #10b981; margin: 0; font-size: 28px; font-weight: 800; letter-spacing: 1px;\">SIGNIA</h2>
                    <p style=\"color: #6b7280; font-size: 14px; margin-top: 4px;\">Filipino Sign Language Learning Platform</p>
                </div>

                <div style=\"background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 16px 20px; margin-bottom: 24px;\">
                    <h3 style=\"color: #166534; font-size: 16px; margin: 0 0 4px 0; font-weight: 700;\">Refund Request Received</h3>
                    <p style=\"color: #15803d; font-size: 13px; margin: 0;\">We are currently reviewing your request. Please allow 1-3 business days.</p>
                </div>
                
                <h3 style=\"color: #1f2937; font-size: 17px; margin-bottom: 12px;\">Hello {$userName},</h3>
                <p style=\"color: #4b5563; font-size: 14px; line-height: 1.6; margin-bottom: 20px;\">
                    We have received your refund request for <strong>Order #{$orderId}</strong>. Our support administration team is reviewing the transaction against our refund policy.
                </p>
                
                <div style=\"background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; margin-bottom: 24px;\">
                    <table style=\"width: 100%; border-collapse: collapse; font-size: 13px;\">
                        <tr>
                            <td style=\"padding: 6px 0; color: #6b7280;\">Order ID:</td>
                            <td style=\"padding: 6px 0; font-weight: 700; color: #111827; text-align: right;\">#{$orderId}</td>
                        </tr>
                        <tr>
                            <td style=\"padding: 6px 0; color: #6b7280;\">Refund Amount:</td>
                            <td style=\"padding: 6px 0; font-weight: 800; color: #059669; text-align: right;\">₱{$amount} {$currency}</td>
                        </tr>
                        <tr>
                            <td style=\"padding: 6px 0; color: #6b7280;\">Reason:</td>
                            <td style=\"padding: 6px 0; font-weight: 600; color: #374151; text-align: right;\">{$reason}</td>
                        </tr>
                        <tr>
                            <td style=\"padding: 6px 0; color: #6b7280; vertical-align: top;\">Notes:</td>
                            <td style=\"padding: 6px 0; color: #4b5563; text-align: right; max-width: 250px;\">{$explanation}</td>
                        </tr>
                    </table>
                </div>

                <div style=\"border-left: 3px solid #10b981; padding-left: 14px; margin-bottom: 24px;\">
                    <p style=\"color: #374151; font-size: 12px; line-height: 1.5; margin: 0;\">
                        <strong>What happens next?</strong> Once approved, the refund will be issued via PayMongo back to your original payment method (GCash, Maya, or Card). PayMongo standard processing typically reflects in 5-10 banking days.
                    </p>
                </div>
                
                <hr style=\"border: none; border-top: 1px solid #f3f4f6; margin: 24px 0;\" />
                
                <p style=\"color: #9ca3af; font-size: 12px; text-align: center; margin: 0;\">
                    © {$year} Signia. All rights reserved.
                </p>
            </div>
            ",
        );
    }
}
