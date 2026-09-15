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

        Sanctum::actingAs($player);

        // Submit choice ID that does not exist for this question
        $response = $this->postJson("/api/user/quizzes/{$quiz->id}/questions/{$question->id}/submit", [
            'answer' => 99999,
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
}
