<?php

namespace Tests\Feature;

use App\Models\Achievement;
use App\Models\GestureLog;
use App\Models\Level;
use App\Models\Progress;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Sign;
use App\Models\Story;
use App\Models\User;
use App\Models\UserAchievement;
use App\Models\UserStoryProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_activities_returns_rich_and_deduplicated_stream(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        // 1. Unlocked achievement
        $achievement = Achievement::create([
            'name' => 'Sharp Signer',
            'description' => 'Accurately perform signs',
            'condition_type' => 'completed_signs',
            'condition_value' => 10,
        ]);
        UserAchievement::create([
            'user_id' => $user->id,
            'achievement_id' => $achievement->id,
            'unlocked_at' => now()->subMinutes(10),
        ]);

        // 2. Story quest completed
        $story = Story::create([
            'slug' => 'cafe-ordering',
            'title' => 'Ordering at a Café',
            'description' => 'Learn coffee signs',
            'chapter_number' => 1,
            'order' => 1,
            'is_free' => true,
        ]);
        UserStoryProgress::create([
            'user_id' => $user->id,
            'story_id' => $story->id,
            'status' => 'completed',
            'completed_at' => now()->subMinutes(8),
        ]);

        // 3. Level & Sign Progress (Letter E)
        $level = Level::create([
            'name' => 'Alphabet',
            'order' => 1,
            'difficulty' => 'easy',
        ]);
        $signE = Sign::create([
            'level_id' => $level->id,
            'name' => 'Letter E',
            'difficulty' => 'easy',
        ]);
        Progress::create([
            'user_id' => $user->id,
            'sign_id' => $signE->id,
            'level_id' => $level->id,
            'is_completed' => true,
            'best_confidence' => 95.0,
            'updated_at' => now()->subMinutes(5),
        ]);

        // 4. Raw gesture log for Letter E (should be deduplicated because Letter E was already completed)
        GestureLog::create([
            'user_id' => $user->id,
            'level_id' => $level->id,
            'sign_id' => $signE->id,
            'expected_sign' => 'E',
            'predicted_sign' => 'E',
            'confidence' => 94,
            'is_correct' => true,
            'created_at' => now()->subMinutes(6),
        ]);

        // Raw gesture log for Letter A (not yet completed, so it should be included)
        $signA = Sign::create([
            'level_id' => $level->id,
            'name' => 'Letter A',
            'difficulty' => 'easy',
        ]);
        GestureLog::create([
            'user_id' => $user->id,
            'level_id' => $level->id,
            'sign_id' => $signA->id,
            'expected_sign' => 'A',
            'predicted_sign' => 'A',
            'confidence' => 88,
            'is_correct' => true,
            'created_at' => now()->subMinutes(4),
        ]);

        // 5. Whole Quiz Attempt
        $quiz = Quiz::create([
            'level_id' => $level->id,
            'title' => 'Alphabet Master Quiz',
            'order' => 1,
        ]);
        QuizAttempt::create([
            'user_id' => $user->id,
            'quiz_id' => $quiz->id,
            'score' => 5,
            'completed_at' => now()->subMinutes(2),
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/user/activities');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => ['id', 'type', 'title', 'status', 'accuracy', 'created_at', 'timestamp'],
                ],
            ]);

        $data = $response->json('data');
        $titles = collect($data)->pluck('title')->all();

        // Must include the high-value milestones
        $this->assertContains('Unlocked Sharp Signer', $titles);
        $this->assertContains('Completed Story: Ordering at a Café', $titles);
        $this->assertContains('Completed Letter E', $titles);
        $this->assertContains('Quiz: Alphabet Master Quiz', $titles);
        $this->assertContains('Practiced Letter A', $titles);

        // Crucial: Must NOT contain redundant "Practiced Letter E" since Letter E was already completed!
        $this->assertNotContains('Practiced Letter E', $titles);
    }

    public function test_per_question_quiz_attempts_deduplicate_repeated_signs(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $level = Level::create([
            'name' => 'Alphabet',
            'order' => 1,
            'difficulty' => 'easy',
        ]);
        $sign = Sign::create([
            'level_id' => $level->id,
            'name' => 'Letter B',
            'difficulty' => 'easy',
        ]);
        $quiz = Quiz::create([
            'level_id' => $level->id,
            'title' => 'Alphabet Practice Quiz',
            'order' => 1,
        ]);
        $question = $quiz->questions()->create([
            'sign_id' => $sign->id,
            'question_type' => 'mcq',
            'question_text' => 'What sign is this?',
            'correct_answer' => 'Letter B',
        ]);

        // Simulate 3 question attempts on the same sign
        QuizAttempt::create([
            'user_id' => $user->id,
            'quiz_id' => $quiz->id,
            'question_id' => $question->id,
            'score' => 1,
            'completed_at' => now()->subMinutes(3),
        ]);
        QuizAttempt::create([
            'user_id' => $user->id,
            'quiz_id' => $quiz->id,
            'question_id' => $question->id,
            'score' => 1,
            'completed_at' => now()->subMinutes(2),
        ]);
        QuizAttempt::create([
            'user_id' => $user->id,
            'quiz_id' => $quiz->id,
            'question_id' => $question->id,
            'score' => 1,
            'completed_at' => now()->subMinutes(1),
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/user/activities');

        $response->assertStatus(200);
        $data = $response->json('data');
        $quizItems = collect($data)->filter(fn($item) => $item['type'] === 'quiz');

        // Must only show 1 entry for Quiz: Letter B instead of 3 identical spam rows
        $this->assertCount(1, $quizItems);
        $this->assertEquals('Quiz: Letter B', $quizItems->first()['title']);
    }
}

