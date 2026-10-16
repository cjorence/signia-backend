<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\UserSupportMessageMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SupportController extends Controller
{
    public function sendMessage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => ['required', 'string', 'in:suggestion,bug_report,general_inquiry,billing_issue'],
            'subject' => ['required', 'string', 'min:3', 'max:150'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $user = Auth::user();
        $supportEmail = config('mail.support_address', 'signiafsl@gmail.com');

        try {
            Mail::to($supportEmail)->send(
                new UserSupportMessageMail(
                    user: $user,
                    category: $validated['category'],
                    subjectLine: $validated['subject'],
                    userMessage: $validated['message']
                )
            );

            // Send confirmation receipt copy to the user's inbox
            if ($user->email) {
                try {
                    Mail::to($user->email)->send(
                        new \App\Mail\SupportMessageConfirmationMail(
                            user: $user,
                            category: $validated['category'],
                            subjectLine: $validated['subject'],
                            userMessage: $validated['message']
                        )
                    );
                } catch (\Throwable $receiptEx) {
                    // Log receipt error without failing the main submission
                    Log::warning('Failed to send support confirmation receipt to user', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'error' => $receiptEx->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send in-app support email', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to send message right now. You can also email us directly at ' . $supportEmail,
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Your message has been sent directly to the Signia team! A confirmation copy has also been sent to your email (' . $user->email . ').',
        ], 200);
    }
}
