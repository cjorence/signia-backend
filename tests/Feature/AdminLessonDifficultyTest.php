<?php

namespace Tests\Feature;

use App\Models\{Level, Question, Quiz, QuizAttempt, User};
use App\Services\AdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminLessonDifficultyTest extends TestCase
{
    use RefreshDatabase;

    private function question(string $name, int $order): Question
    {
        $level = Level::create(['name' => $name, 'order' => $order, 'required_xp' => 0]);
        $quiz = Quiz::create(['level_id' => $level->id, 'title' => $name, 'is_active' => true]);

        return Question::create(['quiz_id' => $quiz->id, 'question_text' => 'Choose a sign', 'question_type' => 'mcq', 'correct_answer' => 'A']);
    }

    private function attempts(Question $question, array $players, int $count, int $incorrect): void
    {
        for ($i = 0; $i < $count; $i++) {
            QuizAttempt::create([
                'user_id' => $players[$i % count($players)]->id,
                'quiz_id' => $question->quiz_id,
                'question_id' => $question->id,
                'score' => $i < $incorrect ? 0 : 1,
                'completed_at' => now(),
            ]);
        }
    }

    public function test_admin_analytics_ranks_by_rate_and_marks_small_samples_without_a_rate(): void
    {
        $this->freezeTime();
        $players = User::factory()->count(3)->create(['role' => 'user'])->all();
        $frequent = $this->question('Alphabet', 1);
        $difficult = $this->question('Numbers', 2);
        $small = $this->question('Greetings', 3);
        $single = $this->question('Colors', 4);
        $this->attempts($frequent, $players, 20, 8);
        $this->attempts($difficult, $players, 10, 6);
        $this->attempts($small, $players, 9, 9);
        $this->attempts($single, [$players[0]], 15, 15);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $response = $this->getJson('/api/admin/analytics')->assertOk();
        $summary = $response->json('data.lesson_difficulty');
        $this->assertSame(30, $summary['window_days']);
        $this->assertSame(10, $summary['minimum_attempts']);
        $this->assertSame(3, $summary['minimum_players']);
        $lessons = $summary['lessons'];
        $this->assertSame('Level 2 · Numbers', $lessons[0]['title']);
        $this->assertEquals(60, $lessons[0]['incorrect_rate']);
        $this->assertSame(10, $lessons[0]['attempt_count']);
        $this->assertSame(3, $lessons[0]['player_count']);
        $this->assertSame(6, $lessons[0]['incorrect_count']);
        $this->assertEquals(40, $lessons[1]['incorrect_rate']);
        $this->assertFalse($lessons[2]['has_enough_data']);
        $this->assertNull($lessons[2]['incorrect_rate']);
        $this->assertFalse($lessons[3]['has_enough_data']);
        $this->assertNull($lessons[3]['incorrect_rate']);
    }

    public function test_only_recent_player_question_answers_for_unarchived_levels_are_counted(): void
    {
        $this->freezeTime();
        $players = User::factory()->count(3)->create(['role' => 'user'])->all();
        $question = $this->question('Alphabet', 1);
        $this->attempts($question, $players, 10, 0);
        $base = ['user_id' => $players[0]->id, 'quiz_id' => $question->quiz_id, 'question_id' => $question->id, 'score' => 0, 'completed_at' => now()];
        foreach ([
            ['completed_at' => now()->subDays(30)->subSecond()],
            ['completed_at' => now()->addDay()],
            ['completed_at' => null],
            ['question_id' => null, 'score' => 0],
            ['score' => 3],
            ['user_id' => User::factory()->create(['role' => 'admin'])->id],
        ] as $overrides) {
            QuizAttempt::create(array_merge($base, $overrides));
        }
        // Include an answer exactly at the start of the rolling window.
        QuizAttempt::create(array_merge($base, ['completed_at' => now()->subDays(30)]));
        $archived = $this->question('Archived', 2);
        $this->attempts($archived, $players, 10, 10);
        $archived->quiz->level->delete();

        $lessons = app(AdminService::class)->getAnalytics()['lesson_difficulty']['lessons'];
        $this->assertCount(1, $lessons);
        $this->assertSame(11, $lessons[0]['attempt_count']);
        $this->assertSame(1, $lessons[0]['incorrect_count']);
        $this->assertEquals(9.1, $lessons[0]['incorrect_rate']);
    }

    public function test_empty_data_and_top_five_limit(): void
    {
        $this->freezeTime();
        $this->assertSame([], app(AdminService::class)->getAnalytics()['lesson_difficulty']['lessons']);
        $players = User::factory()->count(3)->create(['role' => 'user'])->all();
        for ($i = 1; $i <= 7; $i++) {
            $this->attempts($this->question("Lesson {$i}", $i), $players, 10, $i);
        }
        $lessons = app(AdminService::class)->getAnalytics()['lesson_difficulty']['lessons'];
        $this->assertCount(5, $lessons);
        $this->assertEquals([70, 60, 50, 40, 30], array_column($lessons, 'incorrect_rate'));
    }
}
