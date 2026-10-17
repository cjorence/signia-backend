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
        $status = $this->refundRequest->status;
        $needsAttention = $status === 'approved' && in_array($this->refundRequest->provider_status, ['failed', 'unknown'], true);

        $subject = match ($status) {
            'refunded' => "Signia: Your Refund for Order #{$orderId} has been Processed",
            'approved' => "Signia: Your Refund Request for Order #{$orderId} was Approved",
            'rejected' => "Signia: Update on your Refund Request for Order #{$orderId}",
            default => "Signia: Refund Status Update for Order #{$orderId}",
        };

        return new Envelope(subject: $needsAttention ? "Signia: Your Refund for Order #{$orderId} Needs Attention" : $subject);
    }

    public function content(): Content
    {
        $userName = htmlspecialchars($this->refundRequest->user->name ?? 'Learner', ENT_QUOTES, 'UTF-8');
        $orderId = $this->refundRequest->purchase_id;
        $amount = number_format((float) $this->refundRequest->amount, 2);
        $currency = htmlspecialchars($this->refundRequest->currency, ENT_QUOTES, 'UTF-8');
        $status = $this->refundRequest->status;
        $isApproved = in_array($status, ['approved', 'refunded'], true);
        $needsAttention = $status === 'approved' && in_array($this->refundRequest->provider_status, ['failed', 'unknown'], true);
        $adminNotes = htmlspecialchars($this->refundRequest->admin_notes ?? '', ENT_QUOTES, 'UTF-8');
        $paymongoRef = htmlspecialchars($this->refundRequest->paymongo_refund_id ?? 'N/A', ENT_QUOTES, 'UTF-8');
        $updatedAt = $this->refundRequest->updated_at?->timezone('Asia/Manila')->format('M d, Y h:i A') ?? now()->timezone('Asia/Manila')->format('M d, Y h:i A');
        $year = date('Y');

        // Status Banner Configuration
        if ($status === 'refunded') {
            $bannerBg = '#ecfdf5';
            $bannerBorder = '#a7f3d0';
            $bannerTitleColor = '#047857';
            $bannerTextColor = '#065f46';
            $bannerTitle = '✓ Refund Issued & Completed';
            $bannerSub = "Your refund of ₱{$amount} {$currency} has been successfully processed through PayMongo back to your original payment method.";
        } elseif ($needsAttention) {
            $bannerBg = '#fffbeb';
            $bannerBorder = '#fde68a';
            $bannerTitleColor = '#92400e';
            $bannerTextColor = '#78350f';
            $bannerTitle = 'Refund needs attention';
            $bannerSub = 'Your refund has not been confirmed. Our team needs to check the payment. You do not need to submit another request.';
        } elseif ($status === 'approved') {
            $bannerBg = '#ecfdf5';
            $bannerBorder = '#a7f3d0';
            $bannerTitleColor = '#047857';
            $bannerTextColor = '#065f46';
            $bannerTitle = '✓ Refund Approved';
            $bannerSub = "Your refund request for ₱{$amount} {$currency} has been approved by our administrators and submitted to PayMongo.";
        } else {
            $bannerBg = '#fef2f2';
            $bannerBorder = '#fecaca';
            $bannerTitleColor = '#b91c1c';
            $bannerTextColor = '#991b1b';
            $bannerTitle = 'Refund Request Not Approved';
            $bannerSub = 'Your refund request could not be approved based on our platform refund policy guidelines.';
        }

        // Admin Note Callout Box (100% mobile responsive)
        $adminNoteHtml = '';
        if ($adminNotes) {
            $noteTitle = $isApproved ? 'Admin Note:' : 'Reason for Decision:';
            $noteBorder = $isApproved ? '#bbf7d0' : '#fecaca';
            $noteBg = $isApproved ? '#f0fdf4' : '#fff5f5';
            $noteTextColor = $isApproved ? '#166534' : '#991b1b';

            $adminNoteHtml = "
                <div style=\"margin-top: 16px; padding: 14px 16px; background: {$noteBg}; border: 1px solid {$noteBorder}; border-radius: 12px; text-align: left;\">
                    <span style=\"display: block; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: {$noteTextColor}; margin-bottom: 6px;\">
                        {$noteTitle}
                    </span>
                    <div style=\"font-size: 13px; line-height: 1.6; color: #1e293b; word-break: break-word; overflow-wrap: break-word;\">
                        {$adminNotes}
                    </div>
                </div>
            ";
        }

        // Refund Reference Block (100% mobile responsive)
        $refundRefRow = '';
        if ($isApproved && $paymongoRef !== 'N/A') {
            $refundRefRow = "
                <div class=\"meta-row\" style=\"padding: 8px 0; border-bottom: 1px solid #f1f5f9;\">
                    <div class=\"meta-label\" style=\"color: #64748b; font-size: 12px; font-weight: 600;\">Refund Reference ID:</div>
                    <div class=\"meta-value\" style=\"font-family: SFMono-Regular, Consolas, Monaco, monospace; font-size: 12px; color: #0f172a; font-weight: 700; word-break: break-all; overflow-wrap: anywhere; margin-top: 2px;\">
                        {$paymongoRef}
                    </div>
                </div>
            ";
        }

        $nextStepsText = $needsAttention ? '' : ($isApproved
            ? '<p style="color: #475569; font-size: 13px; line-height: 1.6; margin: 16px 0 0 0;">
                   💡 <strong>Processing Timeline:</strong> Depending on whether you paid via GCash, Maya, or Card, PayMongo typically posts the funds back to your account within <strong>5 to 10 banking days</strong>.
               </p>'
            : '<p style="color: #475569; font-size: 13px; line-height: 1.6; margin: 16px 0 0 0;">
                   If you believe this decision was made in error or have additional details or screenshots to share, feel free to reply directly to this email or contact us at <a href="mailto:signiafsl@gmail.com" style="color: #059669; font-weight: 700; text-decoration: underline;">signiafsl@gmail.com</a>.
               </p>');

        return new Content(
            htmlString: "
            <!DOCTYPE html>
            <html lang=\"en\">
            <head>
                <meta charset=\"utf-8\">
                <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
                <meta http-equiv=\"X-UA-Compatible\" content=\"IE=edge\">
                <title>Signia Refund Status Update</title>
                <style>
                    body, table, td, p, a, div { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
                    @media only screen and (max-width: 600px) {
                        .email-container { width: 100% !important; padding: 20px 16px !important; border-radius: 12px !important; }
                        .meta-box { padding: 14px !important; }
                        .meta-row { display: block !important; width: 100% !important; }
                        .meta-label { width: 100% !important; margin-bottom: 2px !important; font-size: 11px !important; }
                        .meta-value { width: 100% !important; font-size: 13px !important; }
                    }
                </style>
            </head>
            <body style=\"margin: 0; padding: 20px 10px; background-color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;\">
                <div class=\"email-container\" style=\"max-width: 580px; margin: 0 auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 32px 24px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);\">
                    
                    <!-- Brand Header -->
                    <div style=\"text-align: center; margin-bottom: 24px; border-bottom: 1px solid #f1f5f9; padding-bottom: 16px;\">
                        <h2 style=\"color: #059669; margin: 0; font-size: 26px; font-weight: 900; letter-spacing: 0.5px;\">SIGNIA</h2>
                        <p style=\"color: #64748b; font-size: 13px; margin: 4px 0 0 0;\">Filipino Sign Language Learning Platform</p>
                    </div>

                    <!-- Status Hero Banner -->
                    <div style=\"background: {$bannerBg}; border: 1px solid {$bannerBorder}; border-radius: 14px; padding: 18px 20px; margin-bottom: 24px;\">
                        <h3 style=\"color: {$bannerTitleColor}; font-size: 16px; margin: 0 0 6px 0; font-weight: 800;\">{$bannerTitle}</h3>
                        <p style=\"color: {$bannerTextColor}; font-size: 13px; margin: 0; line-height: 1.5;\">{$bannerSub}</p>
                    </div>

                    <!-- Greeting -->
                    <p style=\"font-size: 15px; color: #1e293b; margin: 0 0 16px 0;\">
                        Hello <strong>{$userName}</strong>,
                    </p>
                    <p style=\"font-size: 13px; color: #475569; margin: 0 0 20px 0; line-height: 1.6;\">
                        Here is the latest update regarding your refund request for <strong>Order #{$orderId}</strong> as of {$updatedAt} (PHT).
                    </p>

                    <!-- Order Details Summary Box -->
                    <div class=\"meta-box\" style=\"background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 18px 20px; margin-bottom: 20px;\">
                        
                        <div class=\"meta-row\" style=\"padding: 6px 0; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;\">
                            <span class=\"meta-label\" style=\"color: #64748b; font-size: 12px; font-weight: 600;\">Order Reference:</span>
                            <span class=\"meta-value\" style=\"color: #0f172a; font-size: 13px; font-weight: 800;\">#{$orderId}</span>
                        </div>

                        <div class=\"meta-row\" style=\"padding: 6px 0; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;\">
                            <span class=\"meta-label\" style=\"color: #64748b; font-size: 12px; font-weight: 600;\">Refund Amount:</span>
                            <span class=\"meta-value\" style=\"color: #059669; font-size: 15px; font-weight: 900;\">₱{$amount} {$currency}</span>
                        </div>

                        {$refundRefRow}

                        {$adminNoteHtml}
                    </div>

                    {$nextStepsText}

                    <!-- Footer -->
                    <div style=\"margin-top: 32px; padding-top: 20px; border-top: 1px solid #f1f5f9; text-align: center; font-size: 12px; color: #94a3b8;\">
                        <p style=\"margin: 0;\">Signia Filipino Sign Language Learning Platform</p>
                        <p style=\"margin: 4px 0 0 0;\">© {$year} Signia. All rights reserved.</p>
                    </div>

                </div>
            </body>
            </html>
            "
        );
    }
}
