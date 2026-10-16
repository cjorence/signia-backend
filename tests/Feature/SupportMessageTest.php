<?php

namespace Tests\Feature;

use App\Mail\UserSupportMessageMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupportMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_send_support_message_directly(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'name' => 'Maria Santos',
            'email' => 'maria@example.com',
            'role' => 'user',
        ]);

        Sanctum::actingAs($user);

        $payload = [
            'category' => 'suggestion',
            'subject' => 'Please add more healthcare signs',
            'message' => 'It would be amazing if you could add signs related to doctor appointments, medicines, and hospital terms.',
        ];

        $response = $this->postJson('/api/user/support/message', $payload);

        $response->assertOk()
            ->assertJsonPath('success', true);

        Mail::assertSent(UserSupportMessageMail::class, function ($mail) use ($user) {
            return $mail->hasTo('signiafsl@gmail.com')
                && $mail->hasReplyTo($user->email, $user->name)
                && $mail->category === 'suggestion'
                && str_contains($mail->envelope()->subject, 'healthcare signs');
        });
    }

    public function test_unauthenticated_user_cannot_send_support_message(): void
    {
        $response = $this->postJson('/api/user/support/message', [
            'category' => 'general_inquiry',
            'subject' => 'Hello team',
            'message' => 'This is a test message to the team.',
        ]);

        $response->assertUnauthorized();
    }

    public function test_validation_fails_for_invalid_category_or_empty_message(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/user/support/message', [
            'category' => 'invalid_category',
            'subject' => 'Hi',
            'message' => 'Short',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['category', 'subject', 'message']);
    }
}
