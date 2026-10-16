<?php

namespace Tests\Feature;

use App\Mail\PasswordResetCodeMail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_request_password_reset_code_for_non_existent_email(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'doesnotexist@gmail.com',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'No account was found with this email address.',
            ]);

        Mail::assertNothingSent();
    }

    public function test_sends_password_reset_code_to_existing_user(): void
    {
        Mail::fake();

        $user = User::create([
            'name'     => 'Juan Dela Cruz',
            'email'    => 'juan@gmail.com',
            'password' => Hash::make('OldPassword1'),
        ]);

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'juan@gmail.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'juan@gmail.com',
        ]);

        Mail::assertSent(PasswordResetCodeMail::class, function ($mail) {
            return $mail->hasTo('juan@gmail.com') && strlen($mail->code) === 6;
        });
    }

    public function test_cannot_reset_password_with_invalid_code(): void
    {
        User::create([
            'name'     => 'Juan Dela Cruz',
            'email'    => 'juan@gmail.com',
            'password' => Hash::make('OldPassword1'),
        ]);

        DB::table('password_reset_tokens')->insert([
            'email'      => 'juan@gmail.com',
            'token'      => Hash::make('123456'),
            'created_at' => Carbon::now(),
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'email'                 => 'juan@gmail.com',
            'code'                  => '999999',
            'password'              => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid verification code. Please check and try again.',
            ]);
    }

    public function test_cannot_reset_password_with_expired_code(): void
    {
        User::create([
            'name'     => 'Juan Dela Cruz',
            'email'    => 'juan@gmail.com',
            'password' => Hash::make('OldPassword1'),
        ]);

        DB::table('password_reset_tokens')->insert([
            'email'      => 'juan@gmail.com',
            'token'      => Hash::make('123456'),
            'created_at' => Carbon::now()->subMinutes(16),
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'email'                 => 'juan@gmail.com',
            'code'                  => '123456',
            'password'              => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Your verification code has expired. Please request a new one.',
            ]);
    }

    public function test_password_validation_rules_enforced_without_requiring_special_character(): void
    {
        User::create([
            'name'     => 'Juan Dela Cruz',
            'email'    => 'juan@gmail.com',
            'password' => Hash::make('OldPassword1'),
        ]);

        DB::table('password_reset_tokens')->insert([
            'email'      => 'juan@gmail.com',
            'token'      => Hash::make('123456'),
            'created_at' => Carbon::now(),
        ]);

        // Missing number
        $res1 = $this->postJson('/api/auth/reset-password', [
            'email'                 => 'juan@gmail.com',
            'code'                  => '123456',
            'password'              => 'NewPassword',
            'password_confirmation' => 'NewPassword',
        ]);
        $res1->assertStatus(422);

        // Missing uppercase
        $res2 = $this->postJson('/api/auth/reset-password', [
            'email'                 => 'juan@gmail.com',
            'code'                  => '123456',
            'password'              => 'newpassword1',
            'password_confirmation' => 'newpassword1',
        ]);
        $res2->assertStatus(422);

        // Under 8 characters
        $res3 = $this->postJson('/api/auth/reset-password', [
            'email'                 => 'juan@gmail.com',
            'code'                  => '123456',
            'password'              => 'Pass1',
            'password_confirmation' => 'Pass1',
        ]);
        $res3->assertStatus(422);

        // Mismatched confirmation
        $res4 = $this->postJson('/api/auth/reset-password', [
            'email'                 => 'juan@gmail.com',
            'code'                  => '123456',
            'password'              => 'NewPassword1',
            'password_confirmation' => 'DifferentPass1',
        ]);
        $res4->assertStatus(422);
    }

    public function test_successfully_resets_password_and_logs_user_in(): void
    {
        $user = User::create([
            'name'     => 'Juan Dela Cruz',
            'email'    => 'juan@gmail.com',
            'password' => Hash::make('OldPassword1'),
            'email_verified_at' => null,
        ]);

        DB::table('password_reset_tokens')->insert([
            'email'      => 'juan@gmail.com',
            'token'      => Hash::make('123456'),
            'created_at' => Carbon::now(),
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'email'                 => 'juan@gmail.com',
            'code'                  => '123456',
            'password'              => 'BrandNewPassword1',
            'password_confirmation' => 'BrandNewPassword1',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Your password has been reset successfully.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'email', 'has_password'],
                    'token',
                    'token_type',
                ],
            ]);

        // Token record must be deleted (single use)
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'juan@gmail.com',
        ]);

        // User password must be updated
        $freshUser = $user->fresh();
        $this->assertTrue(Hash::check('BrandNewPassword1', $freshUser->password));
        $this->assertNotNull($freshUser->email_verified_at);

        // User can now log in with the new password
        $loginRes = $this->postJson('/api/auth/login', [
            'email'    => 'juan@gmail.com',
            'password' => 'BrandNewPassword1',
        ]);

        $loginRes->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_can_verify_valid_password_reset_code(): void
    {
        DB::table('password_reset_tokens')->insert([
            'email'      => 'learner@gmail.com',
            'token'      => Hash::make('654321'),
            'created_at' => Carbon::now(),
        ]);

        $response = $this->postJson('/api/auth/verify-reset-code', [
            'email' => 'learner@gmail.com',
            'code'  => '654321',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Verification code confirmed.',
            ]);

        // Token must still exist so the user can submit the new password next
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'learner@gmail.com',
        ]);
    }

    public function test_cannot_verify_invalid_code(): void
    {
        DB::table('password_reset_tokens')->insert([
            'email'      => 'learner@gmail.com',
            'token'      => Hash::make('654321'),
            'created_at' => Carbon::now(),
        ]);

        $response = $this->postJson('/api/auth/verify-reset-code', [
            'email' => 'learner@gmail.com',
            'code'  => '000000',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid verification code. Please check and try again.',
            ]);
    }

    public function test_workflow_by_workflow_request_code_then_verify_then_set_password(): void
    {
        Mail::fake();

        $user = User::create([
            'name'     => 'Workflow Tester',
            'email'    => 'workflow@gmail.com',
            'password' => Hash::make('OldPass123'),
        ]);

        // Workflow Step 1: User requests code
        $step1 = $this->postJson('/api/auth/forgot-password', [
            'email' => 'workflow@gmail.com',
        ]);
        $step1->assertStatus(200);

        // Retrieve generated code
        $tokenRecord = DB::table('password_reset_tokens')->where('email', 'workflow@gmail.com')->first();
        $this->assertNotNull($tokenRecord);

        $sentCode = null;
        Mail::assertSent(PasswordResetCodeMail::class, function ($mail) use (&$sentCode) {
            $sentCode = $mail->code;
            return true;
        });
        $this->assertNotNull($sentCode);

        // Workflow Step 2: User verifies code
        $step2 = $this->postJson('/api/auth/verify-reset-code', [
            'email' => 'workflow@gmail.com',
            'code'  => $sentCode,
        ]);
        $step2->assertStatus(200);

        // Workflow Step 3: User sets new password
        $step3 = $this->postJson('/api/auth/reset-password', [
            'email'                 => 'workflow@gmail.com',
            'code'                  => $sentCode,
            'password'              => 'UpdatedSecret1',
            'password_confirmation' => 'UpdatedSecret1',
        ]);
        $step3->assertStatus(200);

        // Token is now single-use deleted
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'workflow@gmail.com',
        ]);

        // Check new password is active
        $this->assertTrue(Hash::check('UpdatedSecret1', $user->fresh()->password));
    }
}

