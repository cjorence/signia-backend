<?php

namespace App\Mail;

use App\Models\RefundRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RefundStatusUpdatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public RefundRequest $refundRequest
    ) {}

    public function envelope(): Envelope
    {
        $orderId = $this->refundRequest->purchase_id;
        $isApproved = in_array($this->refundRequest->status, ['approved', 'refunded'], true);
        $subject = $isApproved
            ? "Signia: Your Refund for Order #{$orderId} has been Processed"
            : "Signia: Update on your Refund Request for Order #{$orderId}";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $userName = htmlspecialchars($this->refundRequest->user->name ?? 'Student', ENT_QUOTES, 'UTF-8');
        $orderId = $this->refundRequest->purchase_id;
        $amount = number_format((float) $this->refundRequest->amount, 2);
        $currency = htmlspecialchars($this->refundRequest->currency, ENT_QUOTES, 'UTF-8');
        $status = $this->refundRequest->status;
        $isApproved = in_array($status, ['approved', 'refunded'], true);
        $adminNotes = htmlspecialchars($this->refundRequest->admin_notes ?? '', ENT_QUOTES, 'UTF-8');
        $paymongoRef = htmlspecialchars($this->refundRequest->paymongo_refund_id ?? 'N/A', ENT_QUOTES, 'UTF-8');
        $year = date('Y');

        $statusBanner = $isApproved
            ? "<div style=\"background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 12px; padding: 18px 20px; margin-bottom: 24px;\">
                   <h3 style=\"color: #047857; font-size: 16px; margin: 0 0 4px 0; font-weight: 700;\">✓ Refund Approved & Issued</h3>
                   <p style=\"color: #065f46; font-size: 13px; margin: 0;\">Your refund of ₱{$amount} {$currency} has been submitted through PayMongo.</p>
               </div>"
            : "<div style=\"background: #fef2f2; border: 1px solid #fecaca; border-radius: 12px; padding: 18px 20px; margin-bottom: 24px;\">
                   <h3 style=\"color: #b91c1c; font-size: 16px; margin: 0 0 4px 0; font-weight: 700;\">Refund Request Declined</h3>
                   <p style=\"color: #991b1b; font-size: 13px; margin: 0;\">Your refund request could not be approved at this time.</p>
               </div>";

        $detailsSection = $isApproved
            ? "<div style=\"background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; margin-bottom: 24px;\">
                   <table style=\"width: 100%; border-collapse: collapse; font-size: 13px;\">
                       <tr>
                           <td style=\"padding: 6px 0; color: #6b7280;\">Order Ref:</td>
                           <td style=\"padding: 6px 0; font-weight: 700; color: #111827; text-align: right;\">#{$orderId}</td>
                       </tr>
                       <tr>
                           <td style=\"padding: 6px 0; color: #6b7280;\">Refunded Amount:</td>
                           <td style=\"padding: 6px 0; font-weight: 800; color: #059669; text-align: right;\">₱{$amount} {$currency}</td>
                       </tr>
                       <tr>
                           <td style=\"padding: 6px 0; color: #6b7280;\">PayMongo Refund Ref:</td>
                           <td style=\"padding: 6px 0; font-family: monospace; font-size: 12px; color: #374151; text-align: right;\">{$paymongoRef}</td>
                       </tr>
                       " . ($adminNotes ? "<tr>
                           <td style=\"padding: 6px 0; color: #6b7280; vertical-align: top;\">Admin Note:</td>
                           <td style=\"padding: 6px 0; color: #4b5563; text-align: right; max-width: 250px;\">{$adminNotes}</td>
                       </tr>" : "") . "
                   </table>
               </div>
               <p style=\"color: #4b5563; font-size: 13px; line-height: 1.6; margin-bottom: 20px;\">
                   Funds typically reflect back in your payment account (GCash, Maya, or Bank Card) within <strong>5-10 banking days</strong> according to standard payment network timelines.
               </p>"
            : "<div style=\"background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; margin-bottom: 24px;\">
                   <table style=\"width: 100%; border-collapse: collapse; font-size: 13px;\">
                       <tr>
                           <td style=\"padding: 6px 0; color: #6b7280;\">Order Ref:</td>
                           <td style=\"padding: 6px 0; font-weight: 700; color: #111827; text-align: right;\">#{$orderId}</td>
                       </tr>
                       <tr>
                           <td style=\"padding: 6px 0; color: #6b7280; vertical-align: top;\">Reason for Decision:</td>
                           <td style=\"padding: 6px 0; font-weight: 600; color: #b91c1c; text-align: right; max-width: 250px;\">" . ($adminNotes ?: 'Request does not meet our refund policy criteria (e.g., items consumed or past eligibility window).') . "</td>
                       </tr>
                   </table>
               </div>
               <p style=\"color: #4b5563; font-size: 13px; line-height: 1.6; margin-bottom: 20px;\">
                   If you believe this was made in error or have further questions, please contact our support team.
               </p>";

        return new Content(
            htmlString: "
            <div style=\"font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; max-width: 580px; margin: 0 auto; padding: 32px 24px; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 16px;\">
                <div style=\"text-align: center; margin-bottom: 24px;\">
                    <h2 style=\"color: #10b981; margin: 0; font-size: 28px; font-weight: 800; letter-spacing: 1px;\">SIGNIA</h2>
                    <p style=\"color: #6b7280; font-size: 14px; margin-top: 4px;\">Filipino Sign Language Learning Platform</p>
                </div>

                {$statusBanner}

                <h3 style=\"color: #1f2937; font-size: 17px; margin-bottom: 12px;\">Hello {$userName},</h3>
                
                {$detailsSection}
                
                <hr style=\"border: none; border-top: 1px solid #f3f4f6; margin: 24px 0;\" />
                
                <p style=\"color: #9ca3af; font-size: 12px; text-align: center; margin: 0;\">
                    © {$year} Signia. All rights reserved.
                </p>
            </div>
            ",
        );
    }
}
