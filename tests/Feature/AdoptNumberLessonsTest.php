<?php

namespace Tests\Feature;

use App\Models\{Level, Progress, Question, Quiz, QuizAttempt, Sign, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdoptNumberLessonsTest extends TestCase
{
    use RefreshDatabase;

    private function curriculum(): array
    {
        Storage::fake('local');
        $level = Level::create(['name' => 'FSL Numbers', 'order' => 2]);
        $quiz = Quiz::create(['level_id' => $level->id, 'title' => 'Numbers', 'is_active' => true]);
        $words = ['ONE', 'TWO', 'THREE', 'FOUR', 'FIVE', 'SIX', 'SEVEN', 'EIGHT', 'NINE', 'TEN'];
        $translations = ['Isa', 'Dalawa', 'Tatlo', 'Apat', 'Lima', 'Anim', 'Pito', 'Walo', 'Siyam', 'Sampu'];
        $old = $new = [];
        foreach ($words as $index => $word) {
            $number = $index + 1;
            $old[] = $legacy = Sign::create(['level_id' => $level->id, 'name' => "Number $number",
                'fsl_name' => "$number / {$translations[$index]}", 'sort_order' => 2 * $number - 1]);
            $new[] = $canonical = Sign::create(['level_id' => $level->id, 'name' => ucfirst(strtolower($word)),
                'fsl_name' => $word, 'model_label' => 'number', 'sort_order' => 2 * $number]);
            foreach ([$legacy, $canonical] as $sign) {
                $question = Question::create(['quiz_id' => $quiz->id, 'sign_id' => $sign->id,
                    'question_text' => 'What number is shown?', 'question_type' => 'mcq', 'correct_answer' => $sign->fsl_name]);
                $question->choices()->create(['choice_text' => $sign->fsl_name, 'is_correct' => true]);
            }
        }
        return [$level, $quiz, $old, $new];
    }

    public function test_preview_does_not_change_data(): void
    {
        $this->curriculum();
        $this->artisan('signia:adopt-number-lessons')->assertExitCode(0);
        $this->assertSame(20, Sign::count());
        $this->assertSame(20, Question::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_apply_keeps_canonical_ids_quizzes_and_history_and_saves_backup(): void
    {
        [$level, $quiz, $old, $new] = $this->curriculum();
        $user = User::factory()->create();
        Progress::create(['user_id' => $user->id, 'sign_id' => $new[0]->id, 'level_id' => $level->id,
            'is_completed' => true, 'best_confidence' => 99, 'attempts' => 3, 'xp_awarded_at' => now()]);
        $this->artisan('signia:adopt-number-lessons', ['--apply' => true])->assertExitCode(0);
        $this->assertSame(10, Sign::count());
        $this->assertSame(20, Sign::withTrashed()->count());
        $this->assertSame(10, Question::count());
        $this->assertSame(range(1, 10), Sign::orderBy('sort_order')->pluck('sort_order')->all());
        $this->assertTrue($old[0]->fresh()->trashed());
        $this->assertDatabaseHas('progress', ['sign_id' => $new[0]->id, 'is_completed' => true, 'attempts' => 3]);
        $this->getJson("/api/levels/{$level->id}/signs")->assertOk()->assertJsonCount(10, 'data')->assertJsonPath('data.0.fsl_name', 'ONE');
        $this->getJson("/api/levels/{$level->id}/quizzes")->assertOk()->assertJsonCount(10, 'data.0.questions');
        $files = Storage::disk('local')->allFiles('curriculum-backups');
        $this->assertCount(1, $files);
        $backup = json_decode(Storage::disk('local')->get($files[0]), true);
        $this->assertCount(20, $backup['signs']);
        $this->assertCount(10, $backup['questions']);
        $this->assertCount(10, $backup['choices']);
        $this->artisan('signia:adopt-number-lessons', ['--apply' => true])->assertExitCode(0);
        $this->assertCount(1, Storage::disk('local')->allFiles('curriculum-backups'));
    }

    public function test_legacy_progress_blocks_cleanup_without_partial_changes(): void
    {
        [$level, , $old] = $this->curriculum();
        $user = User::factory()->create();
        Progress::create(['user_id' => $user->id, 'sign_id' => $old[9]->id, 'level_id' => $level->id]);
        $this->artisan('signia:adopt-number-lessons', ['--apply' => true])->assertExitCode(1);
        $this->assertSame(20, Sign::count());
        $this->assertSame(20, Question::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_legacy_quiz_attempt_blocks_cleanup(): void
    {
        [, $quiz, $old] = $this->curriculum();
        QuizAttempt::create(['user_id' => User::factory()->create()->id, 'quiz_id' => $quiz->id,
            'question_id' => $old[0]->questions()->first()->id, 'score' => 100, 'completed_at' => now()]);
        $this->artisan('signia:adopt-number-lessons', ['--apply' => true])->assertExitCode(1);
        $this->assertSame(20, Sign::count());
        $this->assertSame(20, Question::count());
    }

    public function test_missing_replacement_question_blocks_cleanup(): void
    {
        [, , , $new] = $this->curriculum();
        $new[9]->questions()->delete();
        $this->artisan('signia:adopt-number-lessons', ['--apply' => true])->assertExitCode(1);
        $this->assertSame(20, Sign::count());
        $this->assertSame(19, Question::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }
}
