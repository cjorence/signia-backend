<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Mail\PasswordResetCodeMail;
use App\Mail\VerificationCodeMail;
use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Services\AuthService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {}

    public function sendVerificationCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'           => ['required', 'string', 'email', 'max:255'],
            'name'            => ['sometimes', 'nullable', 'string', 'max:255'],
            'turnstile_token' => ['sometimes', 'nullable', 'string'],
        ]);

        $email = strtolower(trim($validated['email']));
        $name = trim($validated['name'] ?? '') ?: 'there';

        // Check if an account already exists with this email
        if (User::where('email', $email)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'An account with this email already exists. Please log in instead.',
                'errors'  => [
                    'email' => ['An account with this email already exists.'],
                ],
            ], 422);
        }

        // Validate that the email host exists and accepts incoming email (prevent bounced emails)
        if (! $this->isValidEmailHost($email)) {
            return response()->json([
                'success' => false,
                'message' => 'Enter a valid email address.',
                'errors'  => [
                    'email' => ['Enter a valid email address.'],
                ],
            ], 422);
        }

        // Verify Cloudflare Turnstile bot protection token if configured
        // (Allows resend if the user already initiated a valid verification code within 15 min)
        $turnstileSecret = config('services.turnstile.secret_key');
        $hasActiveCode = EmailVerificationCode::where('email', $email)
            ->where('expires_at', '>', now())
            ->exists();

        if (!empty($turnstileSecret) && !app()->environment('testing') && !$hasActiveCode) {
            $turnstileToken = $request->input('turnstile_token');
            if (empty($turnstileToken)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Security verification required. Please try again.',
                    'errors'  => [
                        'turnstile' => ['Security verification required.'],
                    ],
                ], 422);
            }

            if (! $this->verifyTurnstileToken($turnstileToken, $request->ip())) {
                return response()->json([
                    'success' => false,
                    'message' => 'Security check failed. Please refresh the page and try again.',
                    'errors'  => [
                        'turnstile' => ['Security check failed. Please refresh and try again.'],
                    ],
                ], 422);
            }
        }

        // Generate 6-digit code
        $code = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

        // Remove old codes for this email
        EmailVerificationCode::where('email', $email)->delete();

        // Save fresh code with 15-minute validity
        EmailVerificationCode::create([
            'email'      => $email,
            'code'       => $code,
            'expires_at' => now()->addMinutes(15),
        ]);

        // Send email
        try {
            Mail::to($email)->send(new VerificationCodeMail($code, $name));
        } catch (\Throwable $e) {
            Log::warning('Verification email delivery failed', ['exception_type' => get_class($e)]);
            EmailVerificationCode::where('email', $email)->where('code', $code)->delete();

            return response()->json([
                'success' => false,
                'message' => 'Unable to send the verification email. Please try again shortly.',
            ], 503);
        }

        return response()->json([
            'success' => true,
            'message' => "Verification code sent to {$email}.",
        ], 200);
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $code = $request->input('code');

        // Require code in non-testing environments if code is submitted or in production
        if (empty($code) && !app()->environment('testing')) {
            return response()->json([
                'success' => false,
                'message' => 'A verification code is required to complete registration.',
                'errors'  => [
                    'code' => ['A verification code is required to complete registration.'],
                ],
            ], 422);
        }

        if (!empty($code)) {
            $validCode = EmailVerificationCode::where('email', $validated['email'])
                ->where('code', trim($code))
                ->where('expires_at', '>', now())
                ->first();

            if (!$validCode) {
                return response()->json([
                    'success' => false,
                    'message' => 'The verification code is invalid or has expired. Please request a new code.',
                    'errors'  => [
                        'code' => ['The verification code is invalid or has expired.'],
                    ],
                ], 422);
            }

            // Remove code upon successful verification
            $validCode->delete();
        }

        $result = $this->authService->register($validated);

        return response()->json([
            'success'    => true,
            'message'    => 'Registration successful.',
            'data'       => [
                'user'       => new UserResource($result['user']),
                'token'      => $result['token'],
                'token_type' => 'Bearer',
            ],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());

        if ($result['status'] === 'invalid') {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password.',
            ], 401);
        }

        if ($result['status'] === 'google_only') {
            return response()->json([
                'success' => false,
                'message' => 'This account was created with Google. Please use "Continue with Google" to log in, or set a password in your account profile.',
            ], 422);
        }

        if ($result['status'] === 'inactive') {
            return response()->json([
                'success' => false,
                'message' => 'Your account has been deactivated. Please contact support.',
            ], 403);
        }

        return response()->json([
            'success'    => true,
            'message'    => 'Login successful.',
            'data'       => [
                'user'       => new UserResource($result['user']),
                'token'      => $result['token'],
                'token_type' => 'Bearer',
            ],
        ], 200);
    }

    public function sendPasswordResetCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'           => ['required', 'string', 'email', 'max:255'],
            'turnstile_token' => ['sometimes', 'nullable', 'string'],
        ]);

        $email = strtolower(trim($validated['email']));

        $user = User::where('email', $email)->first();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No account was found with this email address.',
                'errors'  => [
                    'email' => ['No account was found with this email address.'],
                ],
            ], 404);
        }

        if (!$this->isValidEmailHost($email)) {
            return response()->json([
                'success' => false,
                'message' => 'Enter a valid email address.',
                'errors'  => [
                    'email' => ['Enter a valid email address.'],
                ],
            ], 422);
        }

        // Verify Turnstile bot protection if configured
        $turnstileSecret = config('services.turnstile.secret_key');
        $hasActiveCode = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->where('created_at', '>', Carbon::now()->subMinutes(15))
            ->exists();

        if (!empty($turnstileSecret) && !app()->environment('testing') && !$hasActiveCode) {
            $turnstileToken = $request->input('turnstile_token');
            if (empty($turnstileToken)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Security verification required. Please try again.',
                    'errors'  => [
                        'turnstile' => ['Security verification required.'],
                    ],
                ], 422);
            }

            if (!$this->verifyTurnstileToken($turnstileToken, $request->ip())) {
                return response()->json([
                    'success' => false,
                    'message' => 'Security check failed. Please refresh the page and try again.',
                    'errors'  => [
                        'turnstile' => ['Security check failed.'],
                    ],
                ], 422);
            }
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::table('password_reset_tokens')->where('email', $email)->delete();
        DB::table('password_reset_tokens')->insert([
            'email'      => $email,
            'token'      => Hash::make($code),
            'created_at' => Carbon::now(),
        ]);

        try {
            Mail::to($email)->send(new PasswordResetCodeMail($code, $user->name));
        } catch (\Throwable $e) {
            Log::error('Password reset email delivery failed', ['exception_type' => get_class($e)]);
            return response()->json([
                'success' => false,
                'message' => 'Unable to send password reset code to this email at the moment. Please verify your email or try again later.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'A password reset code has been sent to your email address.',
        ], 200);
    }

    public function verifyPasswordResetCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'code'  => ['required', 'string', 'size:6'],
        ], [
            'code.size' => 'The verification code must be 6 digits.',
        ]);

        $email = strtolower(trim($validated['email']));
        $code = trim($validated['code']);

        $record = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired verification code. Please request a new code.',
                'errors'  => [
                    'code' => ['Invalid or expired verification code.'],
                ],
            ], 422);
        }

        if (Carbon::parse($record->created_at)->addMinutes(15)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            return response()->json([
                'success' => false,
                'message' => 'Your verification code has expired. Please request a new one.',
                'errors'  => [
                    'code' => ['Verification code has expired.'],
                ],
            ], 422);
        }

        if (!Hash::check($code, $record->token)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification code. Please check and try again.',
                'errors'  => [
                    'code' => ['Invalid verification code.'],
                ],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Verification code confirmed.',
        ], 200);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'                 => ['required', 'string', 'email', 'max:255'],
            'code'                  => ['required', 'string', 'size:6'],
            'password'              => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
            ],
            'password_confirmation' => ['required', 'string'],
        ], [
            'password.min'       => 'Password must be at least 8 characters long.',
            'password.regex'     => 'Password must contain at least 1 uppercase letter, 1 lowercase letter, and 1 number.',
            'password.confirmed' => 'Passwords do not match.',
            'code.size'          => 'The verification code must be 6 digits.',
        ]);

        $email = strtolower(trim($validated['email']));
        $code = trim($validated['code']);

        $record = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired password reset code. Please request a new code.',
                'errors'  => [
                    'code' => ['Invalid or expired verification code.'],
                ],
            ], 422);
        }

        if (Carbon::parse($record->created_at)->addMinutes(15)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            return response()->json([
                'success' => false,
                'message' => 'Your verification code has expired. Please request a new one.',
                'errors'  => [
                    'code' => ['Verification code has expired.'],
                ],
            ], 422);
        }

        if (!Hash::check($code, $record->token)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification code. Please check and try again.',
                'errors'  => [
                    'code' => ['Invalid verification code.'],
                ],
            ], 422);
        }

        $user = User::where('email', $email)->first();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Account not found.',
            ], 404);
        }

        $user->password = Hash::make($validated['password']);
        if (!$user->email_verified_at) {
            $user->email_verified_at = Carbon::now();
        }
        $user->save();

        // Single-use guarantee: remove the token immediately
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        // Invalidate old active sessions/tokens
        $user->tokens()->delete();

        // Generate fresh token
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Your password has been reset successfully.',
            'data'    => [
                'user'       => new UserResource($user),
                'token'      => $token,
                'token_type' => 'Bearer',
            ],
        ], 200);
    }


    public function google(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'credential'   => ['nullable', 'string'],
            'id_token'     => ['nullable', 'string'],
            'access_token' => ['nullable', 'string'],
        ]);

        $credential = $validated['credential'] ?? $validated['id_token'] ?? null;
        $accessToken = $validated['access_token'] ?? null;

        if (!$credential && !$accessToken) {
            return response()->json([
                'success' => false,
                'message' => 'No Google credential or access token provided.',
            ], 422);
        }

        $googleData = null;
        $clientId = (string) config('services.google.client_id');
        if ($clientId === '') {
            return response()->json(['message' => 'Google sign-in is not configured. Please contact support.'], 503);
        }

        // 1. Verify via ID Token (Google Identity Services JWT credential)
        if ($credential) {
            try {
                $response = Http::timeout(10)->get('https://oauth2.googleapis.com/tokeninfo', [
                    'id_token' => $credential,
                ]);

                if ($response->successful()) {
                    $json = $response->json();
                    if (($json['aud'] ?? null) === $clientId && !empty($json['sub']) && !empty($json['email'])
                        && in_array($json['email_verified'] ?? null, [true, 'true'], true)) {
                        $googleData = $json;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Google ID token verification request failed.');
            }
        }

        // 2. Direct verification via access_token
        if (!$googleData && $accessToken) {
            try {
                $tokenInfo = Http::timeout(10)->get('https://oauth2.googleapis.com/tokeninfo', ['access_token' => $accessToken]);
                if (!$tokenInfo->successful() || ($tokenInfo->json('aud') ?? $tokenInfo->json('issued_to')) !== $clientId) {
                    return response()->json(['message' => 'Failed to verify Google account. Please try again.'], 401);
                }
                $response = Http::timeout(10)
                    ->withToken($accessToken)
                    ->get('https://www.googleapis.com/oauth2/v3/userinfo');

                if ($response->successful()) {
                    $json = $response->json();
                    if (!empty($json['sub']) && !empty($json['email'])
                        && in_array($json['email_verified'] ?? null, [true, 'true'], true)) {
                        $googleData = $json;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Google userinfo verification request failed.');
            }
        }

        if (!$googleData) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify Google account. Please try again.',
            ], 401);
        }

        $result = $this->authService->handleGoogleAuth($googleData, $request->only([
            'terms_accepted', 'privacy_acknowledged', 'terms_version', 'privacy_version',
        ]));

        if ($result['status'] === 'linking_required') {
            return response()->json(['message' => 'Sign in using your existing account method. This Google identity cannot be automatically linked to that account.'], 409);
        }

        if ($result['status'] === 'agreement_required') {
            return response()->json([
                'success' => false,
                'code' => 'agreement_required',
                'message' => 'Review and accept the Terms of Service and acknowledge the Privacy Notice to create your Signia account.',
                'terms_version' => \App\Support\LegalAgreement::TERMS_VERSION,
                'privacy_version' => \App\Support\LegalAgreement::PRIVACY_VERSION,
            ], 409);
        }

        if ($result['status'] === 'inactive') {
            return response()->json([
                'success' => false,
                'message' => 'Your account has been deactivated. Please contact support.',
            ], 403);
        }

        return response()->json([
            'success'    => true,
            'message'    => 'Google authentication successful.',
            'data'       => [
                'user'       => new UserResource($result['user']),
                'token'      => $result['token'],
                'token_type' => 'Bearer',
            ],
        ], 200);
    }

    public function logout(): JsonResponse
    {
        $this->authService->logout(auth()->user());

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ], 200);
    }

    public function me(): JsonResponse
    {
        $user = $this->authService->me(auth()->user());

        return response()->json([
            'success' => true,
            'data'    => [
                'user' => new UserResource($user),
            ],
        ], 200);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $rules = [
            'name'     => ['sometimes', 'string', 'max:255'],
            'avatar'   => ['sometimes', 'nullable', 'string', 'max:500'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'regex:/[a-z]/', 'regex:/[A-Z]/', 'regex:/[0-9]/'],
        ];

        // If user already has a password set, require current_password when updating password
        if (!empty($user->password)) {
            $rules['current_password'] = ['required_with:password', 'nullable', 'string'];
        } else {
            $rules['current_password'] = ['nullable', 'string'];
        }

        $messages = [
            'password.min'   => 'The new password must be at least 8 characters.',
            'password.regex' => 'The password must contain at least one uppercase letter, one lowercase letter, and a number.',
            'current_password.required_with' => 'Please enter your current password.',
        ];

        $validated = $request->validate($rules, $messages);

        if (!empty($validated['password'])) {
            if (!empty($user->password)) {
                if (empty($validated['current_password']) || !Hash::check($validated['current_password'], $user->password)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'The current password you entered is incorrect.',
                        'errors' => [
                            'current_password' => ['The current password you entered is incorrect.'],
                        ],
                    ], 422);
                }
            }

            $user->password = $validated['password'];
        }

        if (isset($validated['name']) && trim($validated['name']) !== '') {
            $user->name = trim($validated['name']);
        }

        $user->save();

        if (array_key_exists('avatar', $validated)) {
            $profile = $user->playerProfile;
            if (!$profile) {
                $user->playerProfile()->create([
                    'avatar' => $validated['avatar'],
                    'current_level' => 1,
                    'total_xp' => 0,
                    'streak' => 0,
                ]);
            } else {
                $profile->avatar = $validated['avatar'];
                $profile->save();
            }
        }

        $user = $this->authService->me($user);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data' => [
                'user' => new UserResource($user),
            ],
        ], 200);
    }

    /**
     * Verify Cloudflare Turnstile token with Cloudflare API.
     */
    protected function verifyTurnstileToken(string $token, ?string $ip = null): bool
    {
        $secretKey = config('services.turnstile.secret_key');
        if (empty($secretKey)) {
            return true;
        }

        try {
            $payload = [
                'secret'   => $secretKey,
                'response' => $token,
            ];
            if ($ip) {
                $payload['remoteip'] = $ip;
            }

            $response = Http::asForm()->timeout(5)->post(
                'https://challenges.cloudflare.com/turnstile/v0/siteverify',
                $payload
            );

            if ($response->successful()) {
                $result = $response->json();
                return (bool) ($result['success'] ?? false);
            }
        } catch (\Throwable $e) {
            Log::warning('Turnstile verification failed', ['exception_type' => get_class($e)]);
        }

        return false;
    }

    /**
     * Check if the email domain has valid DNS MX/A records and accepts incoming mail.
     */
    protected function isValidEmailHost(string $email): bool
    {
        if (app()->environment('testing')) {
            return true;
        }

        $domain = substr(strrchr($email, '@'), 1);
        if (!$domain) {
            return false;
        }

        // Check MX records
        $mxRecords = @dns_get_record($domain, DNS_MX);
        if (!empty($mxRecords)) {
            foreach ($mxRecords as $record) {
                $target = trim($record['target'] ?? '');
                // '.' is RFC 7505 Null MX (indicates domain explicitly does not accept mail)
                if ($target !== '' && $target !== '.') {
                    return true;
                }
            }
            return false;
        }

        // Fallback: Check if domain has an A record (RFC 5321 fallback)
        return (bool) @checkdnsrr($domain, 'A');
    }
}
