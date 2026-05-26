<?php

namespace Tests\Feature;

use App\Models\Choice;
use App\Models\Level;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuizSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_update_and_delete_quiz(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $level = Level::create([
            'name' => 'Level 1',
            'description' => 'Basics',
            'order' => 1,
            'required_xp' => 0,
        ]);

        Sanctum::actingAs($admin);

        $createResponse = $this->postJson('/api/admin/quizzes', [
            'level_id' => $level->id,
            'title' => 'Alphabet Quiz',
            'description' => 'Practice signs.',
            'is_active' => true,
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Alphabet Quiz');

        $quizId = $createResponse->json('data.id');

        $this->putJson("/api/admin/quizzes/{$quizId}", [
            'level_id' => $level->id,
            'title' => 'Updated Quiz',
            'description' => 'Updated description.',
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.title', 'Updated Quiz')
            ->assertJsonPath('data.is_active', false);

        $this->deleteJson("/api/admin/quizzes/{$quizId}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('quizzes', ['id' => $quizId]);
    }

    public function test_non_admin_cannot_access_admin_quiz_endpoints(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $level = Level::create(['name' => 'Level 1']);

        Sanctum::actingAs($user);

        $this->postJson('/api/admin/quizzes', [
            'level_id' => $level->id,
            'title' => 'Blocked Quiz',
        ])->assertForbidden();
    }

    public function test_admin_can_add_questions_and_choices(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $quiz = $this->createQuiz();

        Sanctum::actingAs($admin);

        $questionResponse = $this->postJson("/api/admin/quizzes/{$quiz->id}/questions", [
            'question_text' => 'What sign is this?',
            'question_type' => 'mcq',
            'correct_answer' => 'Hello',
        ]);

        $questionResponse->assertCreated()
            ->assertJsonPath('data.question_type', 'mcq');

        $questionId = $questionResponse->json('data.id');

        $this->postJson("/api/admin/questions/{$questionId}/choices", [
            'choice_text' => 'Hello',
            'is_correct' => true,
        ])->assertCreated()
            ->assertJsonPath('data.choice_text', 'Hello')
            ->assertJsonPath('data.is_correct', true);
    }

    public function test_player_quiz_fetch_hides_correct_answers(): void
    {
        $quiz = $this->createQuiz();
        $question = Question::create([
            'quiz_id' => $quiz->id,
            'question_text' => 'Pick the correct answer.',
            'question_type' => 'mcq',
            'correct_answer' => 'Hello',
        ]);

        Choice::create([
            'question_id' => $question->id,
            'choice_text' => 'Hello',
            'is_correct' => true,
        ]);

        $this->getJson("/api/quizzes/{$quiz->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonMissingPath('data.questions.0.correct_answer')
            ->assertJsonMissingPath('data.questions.0.choices.0.is_correct');
    }

    public function test_user_can_submit_mcq_and_text_answers_with_correct_score(): void
    {
        $user = User::factory()->create();
        $quiz = $this->createQuiz();

        $mcqQuestion = Question::create([
            'quiz_id' => $quiz->id,
            'question_text' => 'Choose hello.',
            'question_type' => 'mcq',
            'correct_answer' => 'Hello',
        ]);
        $correctChoice = Choice::create([
            'question_id' => $mcqQuestion->id,
            'choice_text' => 'Hello',
            'is_correct' => true,
        ]);

        $textQuestion = Question::create([
            'quiz_id' => $quiz->id,
            'question_text' => 'Type the sign.',
            'question_type' => 'identification',
            'correct_answer' => 'Salamat',
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/user/quizzes/{$quiz->id}/submit", [
            'answers' => [
                ['question_id' => $mcqQuestion->id, 'answer' => $correctChoice->id],
                ['question_id' => $textQuestion->id, 'answer' => 'sALAMAT'],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.score', 2)
            ->assertJsonPath('data.total_questions', 2);

        $this->assertDatabaseHas('quiz_attempts', [
            'user_id' => $user->id,
            'quiz_id' => $quiz->id,
            'score' => 2,
        ]);
    }

    public function test_invalid_submission_payloads_are_rejected(): void
    {
        $user = User::factory()->create();
        $quiz = $this->createQuiz();
        $otherQuiz = $this->createQuiz();
        $otherQuestion = Question::create([
            'quiz_id' => $otherQuiz->id,
            'question_text' => 'Other quiz question.',
            'question_type' => 'identification',
            'correct_answer' => 'Other',
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/user/quizzes/{$quiz->id}/submit", [
            'answers' => [
                ['question_id' => $otherQuestion->id, 'answer' => 'Other'],
            ],
        ])->assertUnprocessable();
    }

    public function test_inactive_quizzes_cannot_be_submitted(): void
    {
        $user = User::factory()->create();
        $quiz = $this->createQuiz(['is_active' => false]);
        $question = Question::create([
            'quiz_id' => $quiz->id,
            'question_text' => 'Inactive question.',
            'question_type' => 'identification',
            'correct_answer' => 'Hello',
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/user/quizzes/{$quiz->id}/submit", [
            'answers' => [
                ['question_id' => $question->id, 'answer' => 'Hello'],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('quiz');
    }

    private function createQuiz(array $overrides = []): Quiz
    {
        $level = Level::create([
            'name' => 'Level '.uniqid(),
            'description' => 'Quiz level',
            'order' => 1,
            'required_xp' => 0,
        ]);

        return Quiz::create(array_merge([
            'level_id' => $level->id,
            'title' => 'Practice Quiz',
            'description' => 'Practice quiz description',
            'is_active' => true,
        ], $overrides));
    }
}
