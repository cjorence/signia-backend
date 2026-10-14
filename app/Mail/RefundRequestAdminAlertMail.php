<?php

namespace App\Mail;

use App\Models\RefundRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RefundRequestAdminAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public RefundRequest $refundRequest
    ) {}

    public function envelope(): Envelope
    {
        $orderId = $this->refundRequest->purchase_id;
        $userName = $this->refundRequest->user->name ?? 'User';
        return new Envelope(
            subject: "[Action Required] New Refund Request for Order #{$orderId} from {$userName}",
        );
    }

    public function content(): Content
    {
        $userName = htmlspecialchars($this->refundRequest->user->name ?? 'User', ENT_QUOTES, 'UTF-8');
        $userEmail = htmlspecialchars($this->refundRequest->user->email ?? 'N/A', ENT_QUOTES, 'UTF-8');
        $orderId = $this->refundRequest->purchase_id;
        $amount = number_format((float) $this->refundRequest->amount, 2);
        $currency = htmlspecialchars($this->refundRequest->currency, ENT_QUOTES, 'UTF-8');
        $reason = htmlspecialchars(ucwords(str_replace('_', ' ', $this->refundRequest->reason)), ENT_QUOTES, 'UTF-8');
        $explanation = htmlspecialchars($this->refundRequest->explanation ?? 'None provided', ENT_QUOTES, 'UTF-8');
        $createdAt = $this->refundRequest->created_at?->format('M d, Y h:i A') ?? 'Just now';
        $itemTitle = $this->refundRequest->purchase?->product_type === 'story'
            ? "Story Chapter #{$this->refundRequest->purchase->story_id}"
            : "{$this->refundRequest->purchase->quantity} Hearts ({$this->refundRequest->purchase->package_key})";
        $year = date('Y');

        return new Content(
            htmlString: "
            <div style=\"font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; max-width: 580px; margin: 0 auto; padding: 32px 24px; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 16px;\">
                <div style=\"margin-bottom: 24px; border-bottom: 2px solid #ef4444; padding-bottom: 16px;\">
                    <span style=\"display: inline-block; background: #fee2e2; color: #b91c1c; font-size: 11px; font-weight: 800; text-transform: uppercase; padding: 4px 8px; border-radius: 6px; margin-bottom: 8px;\">Admin Action Required</span>
                    <h2 style=\"color: #111827; margin: 0; font-size: 22px; font-weight: 800;\">New Refund Request Submitted</h2>
                    <p style=\"color: #6b7280; font-size: 13px; margin: 4px 0 0 0;\">A customer has submitted a refund claim requiring admin review.</p>
                </div>

                <div style=\"background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; margin-bottom: 24px;\">
                    <table style=\"width: 100%; border-collapse: collapse; font-size: 13px;\">
                        <tr>
                            <td style=\"padding: 6px 0; color: #6b7280;\">Customer:</td>
                            <td style=\"padding: 6px 0; font-weight: 700; color: #111827; text-align: right;\">{$userName} ({$userEmail})</td>
                        </tr>
                        <tr>
                            <td style=\"padding: 6px 0; color: #6b7280;\">Order Ref:</td>
                            <td style=\"padding: 6px 0; font-weight: 700; color: #111827; text-align: right;\">#{$orderId}</td>
                        </tr>
                        <tr>
                            <td style=\"padding: 6px 0; color: #6b7280;\">Item:</td>
                            <td style=\"padding: 6px 0; font-weight: 600; color: #374151; text-align: right;\">{$itemTitle}</td>
                        </tr>
                        <tr>
                            <td style=\"padding: 6px 0; color: #6b7280;\">Amount:</td>
                            <td style=\"padding: 6px 0; font-weight: 800; color: #dc2626; text-align: right;\">₱{$amount} {$currency}</td>
                        </tr>
                        <tr>
                            <td style=\"padding: 6px 0; color: #6b7280;\">Claim Reason:</td>
                            <td style=\"padding: 6px 0; font-weight: 600; color: #374151; text-align: right;\">{$reason}</td>
                        </tr>
                        <tr>
                            <td style=\"padding: 6px 0; color: #6b7280; vertical-align: top;\">User Explanation:</td>
                            <td style=\"padding: 6px 0; color: #4b5563; text-align: right; max-width: 250px;\">{$explanation}</td>
                        </tr>
                        <tr>
                            <td style=\"padding: 6px 0; color: #6b7280;\">Submitted At:</td>
                            <td style=\"padding: 6px 0; color: #6b7280; text-align: right;\">{$createdAt}</td>
                        </tr>
                    </table>
                </div>

                <p style=\"color: #4b5563; font-size: 13px; line-height: 1.5; margin-bottom: 24px;\">
                    Please log in to the <strong>Signia Admin Store Panel</strong> to review this order, inspect the PayMongo transaction ID, and either approve the automated refund or reject with notes.
                </p>
                
                <hr style=\"border: none; border-top: 1px solid #f3f4f6; margin: 24px 0;\" />
                
                <p style=\"color: #9ca3af; font-size: 12px; text-align: center; margin: 0;\">
                    Signia Administrative Alerts • {$year}
                </p>
            </div>
            ",
        );
    }
}
