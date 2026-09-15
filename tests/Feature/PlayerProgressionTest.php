<?php

namespace Tests\Feature;

use App\Models\GestureLog;
use App\Models\Level;
use App\Models\PlayerProfile;
use App\Models\Progress;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Sign;
use App\Models\User;
use App\Services\ProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlayerProgressionTest extends TestCase
{
    use RefreshDatabase;

    private function createProgress(User $user, Sign $sign, Level $level): Progress
    {
        return Progress::create([
            'user_id' => $user->id,
            'sign_id' => $sign->id,
            'level_id' => $level->id,
            'attempts' => 1,
        ]);
    }

    private function addEvidence(User $user, Sign $sign, Level $level): void
    {
        GestureLog::create([
            'user_id' => $user->id,
            'sign_id' => $sign->id,
            'level_id' => $level->id,
            'expected_sign' => $sign->model_label,
            'predicted_sign' => $sign->model_label,
            'confidence' => 95,
            'is_correct' => true,
        ]);

        $quiz = Quiz::create(['level_id' => $level->id, 'title' => 'Sign quiz', 'is_active' => true]);
        $question = Question::create([
            'quiz_id' => $quiz->id,
            'sign_id' => $sign->id,
            'question_text' => 'Identify the sign.',
            'question_type' => 'mcq',
            'correct_answer' => $sign->model_label,
        ]);

        QuizAttempt::create([
            'user_id' => $user->id,
            'quiz_id' => $quiz->id,
            'question_id' => $question->id,
            'score' => 1,
            'completed_at' => now(),
        ]);
    }

    public function test_direct_progress_post_cannot_complete_a_sign_or_award_xp(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        PlayerProfile::create(['user_id' => $user->id, 'current_level' => 1, 'total_xp' => 0, 'hearts' => 5]);
        $level = Level::create(['name' => 'Level 1', 'order' => 1, 'required_xp' => 0]);
        $sign = Sign::create(['level_id' => $level->id, 'name' => 'Letter A', 'model_label' => 'A', 'xp_reward' => 10]);

        Sanctum::actingAs($user);
        $this->postJson('/api/user/progress', [
            'sign_id' => $sign->id,
            'level_id' => $level->id,
            'is_completed' => true,
            'best_confidence' => 100,
        ])->assertOk();

        $this->assertDatabaseHas('progress', ['user_id' => $user->id, 'sign_id' => $sign->id, 'is_completed' => false]);
        $this->assertSame(0, $user->playerProfile->fresh()->total_xp);
    }

    public function test_a_correct_question_cannot_complete_a_different_sign_in_the_same_quiz(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        PlayerProfile::create(['user_id' => $user->id, 'current_level' => 1, 'total_xp' => 0, 'hearts' => 5]);
        $level = Level::create(['name' => 'Level 1', 'order' => 1, 'required_xp' => 0]);
        $signA = Sign::create(['level_id' => $level->id, 'name' => 'Letter A', 'model_label' => 'A', 'xp_reward' => 10]);
        $signB = Sign::create(['level_id' => $level->id, 'name' => 'Letter B', 'model_label' => 'B', 'xp_reward' => 10]);
        $quiz = Quiz::create(['level_id' => $level->id, 'title' => 'Level quiz', 'is_active' => true]);
        $questionA = Question::create(['quiz_id' => $quiz->id, 'sign_id' => $signA->id, 'question_text' => 'A?', 'question_type' => 'mcq', 'correct_answer' => 'A']);
        Question::create(['quiz_id' => $quiz->id, 'sign_id' => $signB->id, 'question_text' => 'B?', 'question_type' => 'mcq', 'correct_answer' => 'B']);
        GestureLog::create(['user_id' => $user->id, 'sign_id' => $signB->id, 'level_id' => $level->id, 'expected_sign' => 'B', 'predicted_sign' => 'B', 'confidence' => 95, 'is_correct' => true]);
        QuizAttempt::create(['user_id' => $user->id, 'quiz_id' => $quiz->id, 'question_id' => $questionA->id, 'score' => 1, 'completed_at' => now()]);
        $this->createProgress($user, $signB, $level);

        app(ProgressService::class)->evaluateCompletion($user->id, $signB->id);

        $this->assertDatabaseHas('progress', ['user_id' => $user->id, 'sign_id' => $signB->id, 'is_completed' => false]);
    }

    public function test_completion_requires_practice_evidence_and_awards_xp_once(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        PlayerProfile::create(['user_id' => $user->id, 'current_level' => 1, 'total_xp' => 0, 'hearts' => 5]);
        $level = Level::create(['name' => 'Level 1', 'order' => 1, 'required_xp' => 0]);
        $sign = Sign::create(['level_id' => $level->id, 'name' => 'Letter A', 'model_label' => 'A', 'xp_reward' => 15]);
        $progress = $this->createProgress($user, $sign, $level);
        $quiz = Quiz::create(['level_id' => $level->id, 'title' => 'Quiz', 'is_active' => true]);
        $question = Question::create(['quiz_id' => $quiz->id, 'sign_id' => $sign->id, 'question_text' => 'A?', 'question_type' => 'mcq', 'correct_answer' => 'A']);
        QuizAttempt::create(['user_id' => $user->id, 'quiz_id' => $quiz->id, 'question_id' => $question->id, 'score' => 1, 'completed_at' => now()]);

        app(ProgressService::class)->evaluateCompletion($user->id, $sign->id);
        $this->assertFalse($progress->fresh()->is_completed);

        GestureLog::create(['user_id' => $user->id, 'sign_id' => $sign->id, 'level_id' => $level->id, 'expected_sign' => 'A', 'predicted_sign' => 'A', 'confidence' => 95, 'is_correct' => true]);
        app(ProgressService::class)->evaluateCompletion($user->id, $sign->id);
        app(ProgressService::class)->evaluateCompletion($user->id, $sign->id);

        $this->assertTrue($progress->fresh()->is_completed);
        $this->assertNotNull($progress->fresh()->xp_awarded_at);
        $this->assertSame(15, $user->playerProfile->fresh()->total_xp);
    }

    public function test_level_thresholds_are_not_mutated_during_completion(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        PlayerProfile::create(['user_id' => $user->id, 'current_level' => 1, 'total_xp' => 0, 'hearts' => 5]);
        $levelOne = Level::create(['name' => 'Level 1', 'order' => 1, 'required_xp' => 0]);
        $levelTwo = Level::create(['name' => 'Level 2', 'order' => 2, 'required_xp' => 0]);
        $sign = Sign::create(['level_id' => $levelOne->id, 'name' => 'Letter A', 'model_label' => 'A', 'xp_reward' => 10]);
        $this->createProgress($user, $sign, $levelOne);
        $this->addEvidence($user, $sign, $levelOne);

        app(ProgressService::class)->evaluateCompletion($user->id, $sign->id);

        $this->assertSame(0, $levelTwo->fresh()->required_xp);
        $this->assertSame(1, $user->playerProfile->fresh()->current_level);
    }
}
