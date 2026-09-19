<?php

namespace Tests\Feature;

use App\Models\Level;
use App\Models\PlayerProfile;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Sign;
use App\Models\User;
use App\Services\ProgressService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StreakSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_first_practice_activity_sets_streak_to_one(): void
    {
        $user = User::factory()->create();
        PlayerProfile::create([
            'user_id' => $user->id,
            'streak' => 0,
            'last_played_date' => null,
        ]);

        $progressService = app(ProgressService::class);
        $streak = $progressService->recordDailyStreak($user->id);

        $this->assertEquals(1, $streak);

        $profile = PlayerProfile::where('user_id', $user->id)->first();
        $this->assertEquals(1, $profile->streak);
        $this->assertTrue(now()->startOfDay()->equalTo($profile->last_played_date));
    }

    public function test_multiple_activities_on_same_day_keep_streak_same(): void
    {
        $user = User::factory()->create();
        PlayerProfile::create([
            'user_id' => $user->id,
            'streak' => 3,
            'last_played_date' => now()->startOfDay(),
        ]);

        $progressService = app(ProgressService::class);
        $streak = $progressService->recordDailyStreak($user->id);

        $this->assertEquals(3, $streak);

        $profile = PlayerProfile::where('user_id', $user->id)->first();
        $this->assertEquals(3, $profile->streak);
    }

    public function test_activity_on_consecutive_day_increments_streak(): void
    {
        $user = User::factory()->create();
        PlayerProfile::create([
            'user_id' => $user->id,
            'streak' => 4,
            'last_played_date' => now()->subDay()->startOfDay(),
        ]);

        $progressService = app(ProgressService::class);
        $streak = $progressService->recordDailyStreak($user->id);

        $this->assertEquals(5, $streak);

        $profile = PlayerProfile::where('user_id', $user->id)->first();
        $this->assertEquals(5, $profile->streak);
        $this->assertTrue(now()->startOfDay()->equalTo($profile->last_played_date));
    }

    public function test_missing_days_resets_streak_to_one_on_next_practice(): void
    {
        $user = User::factory()->create();
        PlayerProfile::create([
            'user_id' => $user->id,
            'streak' => 10,
            'last_played_date' => now()->subDays(3)->startOfDay(),
        ]);

        $progressService = app(ProgressService::class);
        $streak = $progressService->recordDailyStreak($user->id);

        $this->assertEquals(1, $streak);

        $profile = PlayerProfile::where('user_id', $user->id)->first();
        $this->assertEquals(1, $profile->streak);
    }

    public function test_quiz_question_submission_triggers_daily_streak(): void
    {
        $level = Level::create([
            'name' => 'Alphabet',
            'difficulty' => 'easy',
            'order' => 1,
        ]);
        $sign = Sign::create([
            'level_id' => $level->id,
            'name' => 'Sign A',
            'fsl_name' => 'A',
            'model_label' => 'A',
            'order' => 1,
        ]);
        $quiz = Quiz::create([
            'level_id' => $level->id,
            'title' => 'Alphabet Quiz',
            'is_active' => true,
        ]);
        $question = Question::create([
            'quiz_id' => $quiz->id,
            'sign_id' => $sign->id,
            'question_text' => 'Sign A?',
            'question_type' => 'mcq',
            'correct_answer' => 'A',
        ]);

        $user = User::factory()->create();
        PlayerProfile::create([
            'user_id' => $user->id,
            'streak' => 0,
            'hearts' => 5,
            'max_hearts' => 5,
            'last_played_date' => null,
        ]);

        $this->actingAs($user)
            ->postJson("/api/user/quizzes/{$quiz->id}/questions/{$question->id}/submit", [
                'answer_sign_id' => $sign->id,
            ])
            ->assertOk();

        $profile = PlayerProfile::where('user_id', $user->id)->first();
        $this->assertEquals(1, $profile->streak);
    }
}
