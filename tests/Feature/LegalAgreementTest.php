<?php

namespace Tests\Feature;

use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Support\LegalAgreement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LegalAgreementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google.client_id' => 'signia-test-client']);
    }

    private function agreement(): array
    {
        return [
            'terms_accepted' => true,
            'privacy_acknowledged' => true,
            'terms_version' => LegalAgreement::TERMS_VERSION,
            'privacy_version' => LegalAgreement::PRIVACY_VERSION,
        ];
    }

    private function fakeGoogle(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/tokeninfo*' => Http::response([
                'aud' => 'signia-test-client', 'hd' => 'example.com',
                'sub' => 'google-legal-user', 'email' => 'learner@example.com',
                'email_verified' => 'true', 'name' => 'Learner',
            ]),
            'https://www.googleapis.com/oauth2/v3/userinfo' => Http::response([
                'hd' => 'example.com',
                'sub' => 'google-legal-user', 'email' => 'learner@example.com',
                'email_verified' => true, 'name' => 'Learner',
            ]),
        ]);
    }

    public function test_google_verification_alone_creates_no_account_profile_or_token(): void
    {
        $this->fakeGoogle();
        foreach ([['credential' => 'verified-id-token'], ['access_token' => 'verified-access-token']] as $credential) {
            $this->postJson('/api/auth/google', $credential)
                ->assertStatus(409)->assertJsonPath('code', 'agreement_required');
        }
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('player_profiles', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseCount('legal_acceptances', 0);
    }

    public function test_google_rejects_false_or_stale_agreement(): void
    {
        $this->fakeGoogle();
        foreach ([['terms_accepted' => false], ['privacy_acknowledged' => false], ['terms_version' => 'old'], ['privacy_version' => 'old']] as $override) {
            $this->postJson('/api/auth/google', array_merge($this->agreement(), $override, ['credential' => 'valid']))
                ->assertStatus(409);
        }
        $this->assertDatabaseCount('users', 0);
    }

    public function test_google_acceptance_creates_account_with_a_server_dated_record_and_relogin_does_not_overwrite_it(): void
    {
        $this->fakeGoogle();
        $this->freezeTime();
        $this->postJson('/api/auth/google', [...$this->agreement(), 'access_token' => 'valid'])
            ->assertOk()->assertJsonStructure(['data' => ['token']]);
        $user = User::firstOrFail();
        $this->assertDatabaseHas('legal_acceptances', [
            'user_id' => $user->id, 'method' => 'google',
            'terms_version' => LegalAgreement::TERMS_VERSION,
            'privacy_version' => LegalAgreement::PRIVACY_VERSION,
            'accepted_at' => now()->toDateTimeString(),
        ]);
        $this->postJson('/api/auth/google', ['credential' => 'valid'])->assertOk();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('legal_acceptances', 1);
    }

    public function test_existing_user_can_sign_in_without_fabricating_historical_acceptance(): void
    {
        $this->fakeGoogle();
        User::factory()->create(['email' => 'learner@example.com', 'is_active' => true]);
        $this->postJson('/api/auth/google', ['credential' => 'valid'])->assertOk();
        $this->assertDatabaseCount('legal_acceptances', 0);
    }

    public function test_email_signup_cannot_bypass_agreement_and_does_not_consume_code_on_rejection(): void
    {
        EmailVerificationCode::create(['email' => 'learner@example.com', 'code' => '123456', 'expires_at' => now()->addMinutes(10)]);
        $signup = ['name' => 'Learner', 'email' => 'learner@example.com', 'password' => 'Password123!', 'password_confirmation' => 'Password123!', 'code' => '123456'];
        $this->postJson('/api/auth/register', $signup)->assertUnprocessable()->assertJsonValidationErrors(['terms_accepted', 'privacy_acknowledged']);
        $this->postJson('/api/auth/register', array_merge($signup, $this->agreement(), ['terms_version' => 'old']))->assertUnprocessable();
        $this->postJson('/api/auth/register', array_merge($signup, $this->agreement(), ['privacy_acknowledged' => false]))->assertUnprocessable();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('email_verification_codes', 1);
        $response = $this->postJson('/api/auth/register', [...$signup, ...$this->agreement()])->assertCreated();
        $this->assertDatabaseHas('legal_acceptances', ['user_id' => User::firstOrFail()->id, 'method' => 'email', 'terms_version' => LegalAgreement::TERMS_VERSION]);
        $this->withToken($response->json('data.token'))->getJson('/api/auth/me')->assertOk();
    }

    public function test_unverified_google_identity_cannot_create_an_account_even_with_agreement(): void
    {
        Http::fake(['*' => Http::response(['error' => 'invalid_token'], 401)]);
        $this->postJson('/api/auth/google', [...$this->agreement(), 'credential' => 'invalid'])->assertUnauthorized();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('legal_acceptances', 0);
    }
}
