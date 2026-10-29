<?php

namespace Tests\Feature;

use App\Mail\VerificationCodeMail;
use App\Models\EmailVerificationCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegistrationVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_request_verification_code_for_signup(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/auth/send-code', [
            'email' => 'newlearner@gmail.com',
            'name'  => 'Maria Santos',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('email_verification_codes', [
            'email' => 'newlearner@gmail.com',
        ]);

        Mail::assertSent(VerificationCodeMail::class, function ($mail) {
            return $mail->hasTo('newlearner@gmail.com');
        });
    }

    public function test_cannot_request_code_if_email_is_already_registered(): void
    {
        User::create([
            'name'     => 'Existing Person',
            'email'    => 'existing@gmail.com',
            'password' => 'Password123!',
        ]);

        $response = $this->postJson('/api/auth/send-code', [
            'email' => 'existing@gmail.com',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_registration_requires_valid_verification_code(): void
    {
        EmailVerificationCode::create([
            'email'      => 'learner@gmail.com',
            'code'       => '654321',
            'expires_at' => now()->addMinutes(15),
        ]);

        // Wrong code fails
        $failResponse = $this->postJson('/api/auth/register', [
            'terms_accepted' => true,
            'privacy_acknowledged' => true,
            'terms_version' => \App\Support\LegalAgreement::TERMS_VERSION,
            'privacy_version' => \App\Support\LegalAgreement::PRIVACY_VERSION,
            'name'                  => 'Learner',
            'email'                 => 'learner@gmail.com',
            'password'              => 'Password123!',
            'password_confirmation' => 'Password123!',
            'code'                  => '000000',
        ]);

        $failResponse->assertStatus(422)
            ->assertJson(['success' => false]);

        // Correct code succeeds
        $successResponse = $this->postJson('/api/auth/register', [
            'terms_accepted' => true,
            'privacy_acknowledged' => true,
            'terms_version' => \App\Support\LegalAgreement::TERMS_VERSION,
            'privacy_version' => \App\Support\LegalAgreement::PRIVACY_VERSION,
            'name'                  => 'Learner',
            'email'                 => 'learner@gmail.com',
            'password'              => 'Password123!',
            'password_confirmation' => 'Password123!',
            'code'                  => '654321',
        ]);

        $successResponse->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'user' => [
                        'email' => 'learner@gmail.com',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('users', ['email' => 'learner@gmail.com']);
        $this->assertNotNull(User::where('email', 'learner@gmail.com')->firstOrFail()->email_verified_at);
        $this->assertDatabaseMissing('email_verification_codes', ['email' => 'learner@gmail.com']);
    }
}
