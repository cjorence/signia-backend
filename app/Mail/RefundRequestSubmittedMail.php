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
        $userName = htmlspecialchars($this->refundRequest->user->name ?? 'Learner', ENT_QUOTES, 'UTF-8');
        $orderId = $this->refundRequest->purchase_id;
        $amount = number_format((float) $this->refundRequest->amount, 2);
        $currency = htmlspecialchars($this->refundRequest->currency, ENT_QUOTES, 'UTF-8');
        $reason = htmlspecialchars(ucwords(str_replace('_', ' ', $this->refundRequest->reason)), ENT_QUOTES, 'UTF-8');
        $explanation = htmlspecialchars($this->refundRequest->explanation ?? 'None provided', ENT_QUOTES, 'UTF-8');
        $submittedAt = $this->refundRequest->created_at?->timezone('Asia/Manila')->format('M d, Y h:i A') ?? now()->timezone('Asia/Manila')->format('M d, Y h:i A');
        $year = date('Y');

        return new Content(
            htmlString: "
            <!DOCTYPE html>
            <html lang=\"en\">
            <head>
                <meta charset=\"utf-8\">
                <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
                <meta http-equiv=\"X-UA-Compatible\" content=\"IE=edge\">
                <title>Signia Refund Request Received</title>
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
                    <div style=\"background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 14px; padding: 18px 20px; margin-bottom: 24px;\">
                        <h3 style=\"color: #166534; font-size: 16px; margin: 0 0 6px 0; font-weight: 800;\">Refund Request Received</h3>
                        <p style=\"color: #15803d; font-size: 13px; margin: 0; line-height: 1.5;\">We have received your claim and our administration team is reviewing the transaction against our refund policy.</p>
                    </div>

                    <!-- Greeting -->
                    <p style=\"font-size: 15px; color: #1e293b; margin: 0 0 16px 0;\">
                        Hello <strong>{$userName}</strong>,
                    </p>
                    <p style=\"font-size: 13px; color: #475569; margin: 0 0 20px 0; line-height: 1.6;\">
                        Your refund request for <strong>Order #{$orderId}</strong> was logged on {$submittedAt} (PHT). Here is a summary of your submission:
                    </p>

                    <!-- Order Details Summary Box -->
                    <div class=\"meta-box\" style=\"background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 18px 20px; margin-bottom: 20px;\">
                        
                        <div class=\"meta-row\" style=\"padding: 6px 0; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;\">
                            <span class=\"meta-label\" style=\"color: #64748b; font-size: 12px; font-weight: 600;\">Order Reference:</span>
                            <span class=\"meta-value\" style=\"color: #0f172a; font-size: 13px; font-weight: 800;\">#{$orderId}</span>
                        </div>

                        <div class=\"meta-row\" style=\"padding: 6px 0; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;\">
                            <span class=\"meta-label\" style=\"color: #64748b; font-size: 12px; font-weight: 600;\">Requested Amount:</span>
                            <span class=\"meta-value\" style=\"color: #059669; font-size: 15px; font-weight: 900;\">₱{$amount} {$currency}</span>
                        </div>

                        <div class=\"meta-row\" style=\"padding: 6px 0; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;\">
                            <span class=\"meta-label\" style=\"color: #64748b; font-size: 12px; font-weight: 600;\">Claim Reason:</span>
                            <span class=\"meta-value\" style=\"color: #334151; font-size: 13px; font-weight: 700;\">{$reason}</span>
                        </div>

                        <div style=\"margin-top: 14px; padding-top: 12px; border-top: 1px dashed #e2e8f0; text-align: left;\">
                            <span style=\"display: block; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-bottom: 6px;\">
                                Your Submitted Explanation:
                            </span>
                            <div style=\"background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px 14px; font-size: 13px; line-height: 1.6; color: #1e293b; word-break: break-word; overflow-wrap: break-word;\">
                                {$explanation}
                            </div>
                        </div>

                    </div>

                    <div style=\"background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 14px 16px; margin-bottom: 24px;\">
                        <p style=\"color: #166534; font-size: 12px; line-height: 1.5; margin: 0;\">
                            💡 <strong>What happens next?</strong> Once approved, the refund will be issued via PayMongo directly back to your payment method (GCash, Maya, or Card). PayMongo standard processing typically reflects in 5–10 banking days.
                        </p>
                    </div>

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
