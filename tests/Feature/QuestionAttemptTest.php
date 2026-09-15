<?php

namespace Tests\Feature;

use App\Models\Choice;
use App\Models\HeartTransaction;
use App\Models\Level;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Sign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuestionAttemptTest extends TestCase
{
    use RefreshDatabase;

    public function test_submitted_attempt_stores_quiz_id_and_question_id(): void
    {
        $player = User::factory()->create(['role' => 'user']);
        $player->playerProfile()->create([
            'hearts' => 5,
            'total_xp' => 0,
            'current_level' => 1,
        ]);

        $level = Level::create([
            'name' => 'Alphabet',
            'description' => 'Alphabet',
            'order' => 1,
            'required_xp' => 0,
        ]);

        $quiz = Quiz::create([
            'level_id' => $level->id,
            'title' => 'Alphabet Quiz',
            'is_active' => true,
        ]);

        $question = Question::create([
            'quiz_id' => $quiz->id,
            'question_text' => 'What alphabet letter is shown?',
            'question_type' => 'mcq',
            'correct_answer' => 'A',
            'order' => 1,
        ]);

        $correctChoice = Choice::create([
            'question_id' => $question->id,
            'choice_text' => 'A',
            'is_correct' => true,
            'order' => 1,
        ]);

        Sanctum::actingAs($player);

        $response = $this->postJson("/api/user/quizzes/{$quiz->id}/questions/{$question->id}/submit", [
            'answer' => $correctChoice->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_correct', true)
            ->assertJsonPath('data.score', 1)
            ->assertJsonPath('data.total_questions', 1)
            ->assertJsonPath('data.attempt.quiz_id', $quiz->id)
            ->assertJsonPath('data.attempt.question_id', $question->id);

        $this->assertDatabaseHas('quiz_attempts', [
            'user_id' => $player->id,
            'quiz_id' => $quiz->id,
            'question_id' => $question->id,
            'score' => 1,
        ]);
    }

    public function test_question_cannot_be_submitted_under_another_quiz(): void
    {
        $player = User::factory()->create(['role' => 'user']);
        $player->playerProfile()->create([
            'hearts' => 5,
            'total_xp' => 0,
            'current_level' => 1,
        ]);

        $level = Level::create([
            'name' => 'Alphabet',
            'description' => 'Alphabet',
            'order' => 1,
            'required_xp' => 0,
        ]);

        $quizA = Quiz::create([
            'level_id' => $level->id,
            'title' => 'Quiz A',
            'is_active' => true,
        ]);

        $quizB = Quiz::create([
            'level_id' => $level->id,
            'title' => 'Quiz B',
            'is_active' => true,
        ]);

        $questionA = Question::create([
            'quiz_id' => $quizA->id,
            'question_text' => 'Question for Quiz A',
            'question_type' => 'mcq',
            'correct_answer' => 'A',
            'order' => 1,
        ]);

        $choiceA = Choice::create([
            'question_id' => $questionA->id,
            'choice_text' => 'A',
            'is_correct' => true,
            'order' => 1,
        ]);

        Sanctum::actingAs($player);

        // Attempt to submit Question A under Quiz B
        $response = $this->postJson("/api/user/quizzes/{$quizB->id}/questions/{$questionA->id}/submit", [
            'answer' => $choiceA->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('question');
    }

    public function test_zero_hearts_returns_422(): void
    {
        $player = User::factory()->create(['role' => 'user']);
        $player->playerProfile()->create([
            'hearts' => 0,
            'total_xp' => 0,
            'current_level' => 1,
        ]);

        $level = Level::create([
            'name' => 'Alphabet',
            'description' => 'Alphabet',
            'order' => 1,
            'required_xp' => 0,
        ]);

        $quiz = Quiz::create([
            'level_id' => $level->id,
            'title' => 'Alphabet Quiz',
            'is_active' => true,
        ]);

        $question = Question::create([
            'quiz_id' => $quiz->id,
            'question_text' => 'What alphabet letter is shown?',
            'question_type' => 'mcq',
            'correct_answer' => 'A',
            'order' => 1,
        ]);

        $choice = Choice::create([
            'question_id' => $question->id,
            'choice_text' => 'A',
            'is_correct' => true,
            'order' => 1,
        ]);

        Sanctum::actingAs($player);

        $response = $this->postJson("/api/user/quizzes/{$quiz->id}/questions/{$question->id}/submit", [
            'answer' => $choice->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('hearts');
    }

    public function test_invalid_choice_returns_validation_error(): void
    {
        $player = User::factory()->create(['role' => 'user']);
        $player->playerProfile()->create([
            'hearts' => 5,
            'total_xp' => 0,
            'current_level' => 1,
        ]);

        $level = Level::create([
            'name' => 'Alphabet',
            'description' => 'Alphabet',
            'order' => 1,
            'required_xp' => 0,
        ]);

        $quiz = Quiz::create([
            'level_id' => $level->id,
            'title' => 'Alphabet Quiz',
            'is_active' => true,
        ]);

        $question = Question::create([
            'quiz_id' => $quiz->id,
            'question_text' => 'What alphabet letter is shown?',
            'question_type' => 'mcq',
            'correct_answer' => 'A',
            'order' => 1,
        ]);

        $otherQuestion = Question::create([
            'quiz_id' => $quiz->id,
            'question_text' => 'A different question',
            'question_type' => 'mcq',
            'correct_answer' => 'B',
            'order' => 2,
        ]);
        $otherQuestionChoice = Choice::create([
            'question_id' => $otherQuestion->id,
            'choice_text' => 'B',
            'is_correct' => true,
            'order' => 1,
        ]);

        Sanctum::actingAs($player);

        // Submit a real choice ID that belongs to another question.
        $response = $this->postJson("/api/user/quizzes/{$quiz->id}/questions/{$question->id}/submit", [
            'answer' => $otherQuestionChoice->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('answer');
    }

    public function test_repeated_attempts_follow_intended_heart_rule(): void
    {
        $player = User::factory()->create(['role' => 'user']);
        $player->playerProfile()->create([
            'hearts' => 2,
            'total_xp' => 0,
            'current_level' => 1,
        ]);

        $level = Level::create([
            'name' => 'Alphabet',
            'description' => 'Alphabet',
            'order' => 1,
            'required_xp' => 0,
        ]);

        $quiz = Quiz::create([
            'level_id' => $level->id,
            'title' => 'Alphabet Quiz',
            'is_active' => true,
        ]);

        $question = Question::create([
            'quiz_id' => $quiz->id,
            'question_text' => 'What alphabet letter is shown?',
            'question_type' => 'mcq',
            'correct_answer' => 'A',
            'order' => 1,
        ]);

        $wrongChoice = Choice::create([
            'question_id' => $question->id,
            'choice_text' => 'B',
            'is_correct' => false,
            'order' => 1,
        ]);

        Sanctum::actingAs($player);

        // First attempt (wrong): hearts 2 -> 1
        $response1 = $this->postJson("/api/user/quizzes/{$quiz->id}/questions/{$question->id}/submit", [
            'answer' => $wrongChoice->id,
        ]);
        $response1->assertOk()
            ->assertJsonPath('data.is_correct', false);
        $this->assertEquals(1, $player->playerProfile()->first()->hearts);

        // Second attempt (wrong): hearts 1 -> 0
        $response2 = $this->postJson("/api/user/quizzes/{$quiz->id}/questions/{$question->id}/submit", [
            'answer' => $wrongChoice->id,
        ]);
        $response2->assertOk()
            ->assertJsonPath('data.is_correct', false);
        $this->assertEquals(0, $player->playerProfile()->first()->hearts);

        // Third attempt (0 hearts remaining): rejected with 422
        $response3 = $this->postJson("/api/user/quizzes/{$quiz->id}/questions/{$question->id}/submit", [
            'answer' => $wrongChoice->id,
        ]);
        $response3->assertStatus(422)
            ->assertJsonValidationErrors('hearts');
        $this->assertEquals(0, $player->playerProfile()->first()->hearts);
    }

    public function test_player_quiz_data_returns_the_correct_choice_and_three_random_database_distractors(): void
    {
        $level = Level::create([
            'name' => 'Alphabet',
            'description' => 'Alphabet',
            'order' => 1,
            'required_xp' => 0,
        ]);
        $quiz = Quiz::create(['level_id' => $level->id, 'title' => 'Alphabet Quiz', 'is_active' => true]);
        $question = Question::create([
            'quiz_id' => $quiz->id,
            'question_text' => 'What alphabet letter is shown?',
            'question_type' => 'mcq',
            'correct_answer' => 'A',
        ]);

        foreach (['A' => true, 'B' => false, 'C' => false, 'D' => false, 'E' => false] as $label => $isCorrect) {
            Choice::create([
                'question_id' => $question->id,
                'choice_text' => $label,
                'is_correct' => $isCorrect,
            ]);
        }

        $response = $this->getJson("/api/levels/{$level->id}/quizzes")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(4, 'data.0.questions.0.choices')
            ->assertJsonMissing(['is_correct' => true]);

        $labels = collect($response->json('data.0.questions.0.choices'))
            ->pluck('choice_text');

        $this->assertTrue($labels->contains('A'));
        $this->assertTrue($labels->every(fn (string $label) => in_array($label, ['A', 'B', 'C', 'D', 'E'], true)));
    }

    public function test_wrong_answer_creates_one_loss_log_and_correct_answer_does_not_restore_hearts(): void
    {
        $player = User::factory()->create(['role' => 'user']);
        $player->playerProfile()->create([
            'hearts' => 3,
            'total_xp' => 0,
            'current_level' => 1,
        ]);
        $level = Level::create(['name' => 'Alphabet', 'order' => 1, 'required_xp' => 0]);
        $quiz = Quiz::create(['level_id' => $level->id, 'title' => 'Alphabet Quiz', 'is_active' => true]);
        $question = Question::create([
            'quiz_id' => $quiz->id,
            'question_text' => 'What alphabet letter is shown?',
            'question_type' => 'mcq',
            'correct_answer' => 'A',
        ]);
        $wrongChoice = Choice::create(['question_id' => $question->id, 'choice_text' => 'B', 'is_correct' => false]);
        $correctChoice = Choice::create(['question_id' => $question->id, 'choice_text' => 'A', 'is_correct' => true]);

        Sanctum::actingAs($player);

        $this->postJson("/api/user/quizzes/{$quiz->id}/questions/{$question->id}/submit", [
            'answer' => $wrongChoice->id,
        ])->assertOk()->assertJsonPath('data.is_correct', false);

        $this->assertSame(2, $player->playerProfile()->first()->hearts);
        $this->assertSame(1, HeartTransaction::where('user_id', $player->id)
            ->where('type', 'lost')
            ->where('reason', 'wrong_quiz_answer')
            ->count());

        $this->postJson("/api/user/quizzes/{$quiz->id}/questions/{$question->id}/submit", [
            'answer' => $correctChoice->id,
        ])->assertOk()->assertJsonPath('data.is_correct', true);

        $this->assertSame(2, $player->playerProfile()->first()->hearts);
        $this->assertSame(1, HeartTransaction::where('user_id', $player->id)
            ->where('type', 'lost')
            ->where('reason', 'wrong_quiz_answer')
            ->count());
    }

    public function test_multiple_choice_question_can_store_more_than_four_choices_for_randomized_player_rounds(): void
    {
        $level = Level::create(['name' => 'Alphabet', 'order' => 1, 'required_xp' => 0]);
        $quiz = Quiz::create(['level_id' => $level->id, 'title' => 'Alphabet Quiz', 'is_active' => true]);
        $question = Question::create([
            'quiz_id' => $quiz->id,
            'question_text' => 'What alphabet letter is shown?',
            'question_type' => 'mcq',
            'correct_answer' => 'A',
        ]);

        foreach (['A', 'B', 'C', 'D'] as $label) {
            Choice::create([
                'question_id' => $question->id,
                'choice_text' => $label,
                'is_correct' => $label === 'A',
            ]);
        }

        app(\App\Services\QuizService::class)->addChoice($question, [
            'choice_text' => 'E',
            'is_correct' => false,
        ]);

        $this->assertSame(5, $question->choices()->count());
    }

    public function test_question_sign_must_belong_to_the_quiz_level(): void
    {
        $alphabet = Level::create(['name' => 'Alphabet', 'order' => 1, 'required_xp' => 0]);
        $numbers = Level::create(['name' => 'Numbers', 'order' => 2, 'required_xp' => 100]);
        $quiz = Quiz::create(['level_id' => $alphabet->id, 'title' => 'Alphabet Quiz', 'is_active' => true]);
        $numberSign = Sign::create([
            'level_id' => $numbers->id,
            'name' => 'Number One',
            'model_label' => 'number_one',
            'xp_reward' => 10,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(\App\Services\QuizService::class)->addQuestion($quiz, [
            'sign_id' => $numberSign->id,
            'question_text' => 'What number is shown?',
            'question_type' => 'mcq',
            'correct_answer' => '1',
        ]);
    }

    public function test_player_lesson_options_follow_category_order_for_current_and_future_signs(): void
    {
        $level = Level::create(['name' => 'Alphabet', 'order' => 1, 'required_xp' => 0]);
        $signs = collect(['A', 'B', 'C', 'D', 'E', 'F'])->map(function (string $label, int $index) use ($level) {
            return Sign::create([
                'level_id' => $level->id,
                'name' => "Letter {$label}",
                'fsl_name' => $label,
                'model_label' => "letter_".strtolower($label),
                'xp_reward' => 10,
                'sort_order' => $index + 1,
            ]);
        });
        $quiz = Quiz::create(['level_id' => $level->id, 'title' => 'Alphabet Quiz', 'is_active' => true]);
        $questionA = Question::create([
            'quiz_id' => $quiz->id,
            'sign_id' => $signs[0]->id,
            'question_text' => 'What alphabet letter is shown?',
            'question_type' => 'mcq',
            'correct_answer' => 'A',
        ]);
        $questionE = Question::create([
            'quiz_id' => $quiz->id,
            'sign_id' => $signs[4]->id,
            'question_text' => 'What alphabet letter is shown?',
            'question_type' => 'mcq',
            'correct_answer' => 'E',
        ]);

        $response = $this->getJson("/api/levels/{$level->id}/quizzes")->assertOk();
        $questions = collect($response->json('data.0.questions'))->keyBy('id');
        $labelsForA = collect($questions[$questionA->id]['lesson_options'])->pluck('label')->sort()->values()->all();
        $labelsForE = collect($questions[$questionE->id]['lesson_options'])->pluck('label');

        $this->assertSame(['A', 'B', 'C', 'D'], $labelsForA);
        $this->assertCount(4, $labelsForE);
        $this->assertTrue($labelsForE->contains('E'));
        $this->assertTrue($labelsForE->every(fn (string $label) => in_array($label, ['A', 'B', 'C', 'D', 'E'], true)));
        $this->assertFalse($labelsForE->contains('F'));
    }

    public function test_player_lesson_submission_accepts_only_server_generated_sign_options(): void
    {
        $player = User::factory()->create(['role' => 'user']);
        $player->playerProfile()->create(['hearts' => 3, 'total_xp' => 0, 'current_level' => 1]);
        $level = Level::create(['name' => 'Alphabet', 'order' => 1, 'required_xp' => 0]);
        $signs = collect(['A', 'B', 'C', 'D', 'E', 'F'])->map(function (string $label, int $index) use ($level) {
            return Sign::create([
                'level_id' => $level->id,
                'name' => "Letter {$label}",
                'fsl_name' => $label,
                'model_label' => "letter_".strtolower($label),
                'xp_reward' => 10,
                'sort_order' => $index + 1,
            ]);
        });
        $quiz = Quiz::create(['level_id' => $level->id, 'title' => 'Alphabet Quiz', 'is_active' => true]);
        $question = Question::create([
            'quiz_id' => $quiz->id,
            'sign_id' => $signs[4]->id,
            'question_text' => 'What alphabet letter is shown?',
            'question_type' => 'mcq',
            'correct_answer' => 'E',
        ]);

        Sanctum::actingAs($player);

        $lessonOptions = collect(
            $this->getJson("/api/levels/{$level->id}/quizzes")
                ->assertOk()
                ->json('data.0.questions.0.lesson_options')
        );
        $wrongOption = $lessonOptions->first(
            fn (array $option) => $option['sign_id'] !== $signs[4]->id
        );

        $this->postJson("/api/user/quizzes/{$quiz->id}/questions/{$question->id}/submit", [
            'answer_sign_id' => $signs[5]->id,
        ])->assertStatus(422)->assertJsonValidationErrors('answer_sign_id');

        $this->postJson("/api/user/quizzes/{$quiz->id}/questions/{$question->id}/submit", [
            'answer_sign_id' => $wrongOption['sign_id'],
        ])->assertOk()->assertJsonPath('data.is_correct', false);
        $this->assertSame(2, $player->playerProfile()->first()->hearts);

        $this->postJson("/api/user/quizzes/{$quiz->id}/questions/{$question->id}/submit", [
            'answer_sign_id' => $signs[4]->id,
        ])->assertOk()->assertJsonPath('data.is_correct', true);
        $this->assertSame(2, $player->playerProfile()->first()->hearts);
    }
}
