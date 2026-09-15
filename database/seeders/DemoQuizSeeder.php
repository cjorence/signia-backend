<?php

namespace Database\Seeders;

use App\Models\Choice;
use App\Models\Level;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Sign;
use Illuminate\Database\Seeder;

/**
 * DemoQuizSeeder
 * 
 * Standalone, optional demo seeder for populating sample quizzes on fresh databases.
 * Will NOT update, delete, or recreate quizzes, questions, or choices if quizzes
 * already exist for a level (preserving administrator-created content).
 * Kept strictly out of DatabaseSeeder.php.
 */
class DemoQuizSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAlphabetQuiz();
        $this->seedNumbersQuiz();
        $this->seedGreetingsQuiz();
    }

    protected function seedAlphabetQuiz(): void
    {
        $level = Level::where('name', 'FSL Alphabet')->first()
            ?? Level::where('order', 1)->first();

        if (!$level) {
            return;
        }

        // Preserve existing administrator-created or previously seeded quizzes
        if (Quiz::where('level_id', $level->id)->exists()) {
            return;
        }

        $quiz = Quiz::create([
            'level_id' => $level->id,
            'title' => 'FSL Alphabet Quiz',
            'description' => 'Test your knowledge of Filipino Sign Language alphabet fingerspelling.',
            'is_active' => true,
        ]);

        $signs = Sign::where('level_id', $level->id)->orderBy('sort_order')->get();
        if ($signs->isEmpty()) {
            return;
        }

        $allLetters = $signs->pluck('fsl_name')->filter()->values()->toArray();

        foreach ($signs as $sign) {
            $letter = $sign->fsl_name ?: $sign->name;

            $question = Question::create([
                'quiz_id' => $quiz->id,
                'sign_id' => $sign->id,
                'question_text' => 'What alphabet letter is shown?',
                'question_type' => 'mcq',
                'correct_answer' => $letter,
            ]);

            // Distractors
            $distractors = collect($allLetters)
                ->filter(fn($l) => $l !== $letter)
                ->shuffle()
                ->take(3)
                ->values();

            Choice::create([
                'question_id' => $question->id,
                'choice_text' => $letter,
                'is_correct' => true,
            ]);

            foreach ($distractors as $distractor) {
                Choice::create([
                    'question_id' => $question->id,
                    'choice_text' => $distractor,
                    'is_correct' => false,
                ]);
            }
        }
    }

    protected function seedNumbersQuiz(): void
    {
        $level = Level::where('name', 'FSL Numbers')->first()
            ?? Level::where('order', 2)->first();

        if (!$level) {
            return;
        }

        if (Quiz::where('level_id', $level->id)->exists()) {
            return;
        }

        $quiz = Quiz::create([
            'level_id' => $level->id,
            'title' => 'FSL Numbers Quiz',
            'description' => 'Test your knowledge of Filipino Sign Language numbers from 1 to 10.',
            'is_active' => true,
        ]);

        $signs = Sign::where('level_id', $level->id)->orderBy('sort_order')->get();
        if ($signs->isEmpty()) {
            return;
        }

        $allNumbers = $signs->pluck('fsl_name')->filter()->values()->toArray();

        foreach ($signs as $sign) {
            $number = $sign->fsl_name ?: $sign->name;

            $question = Question::create([
                'quiz_id' => $quiz->id,
                'sign_id' => $sign->id,
                'question_text' => 'What number is being signed?',
                'question_type' => 'mcq',
                'correct_answer' => $number,
            ]);

            $distractors = collect($allNumbers)
                ->filter(fn($n) => $n !== $number)
                ->shuffle()
                ->take(3)
                ->values();

            Choice::create([
                'question_id' => $question->id,
                'choice_text' => $number,
                'is_correct' => true,
            ]);

            foreach ($distractors as $distractor) {
                Choice::create([
                    'question_id' => $question->id,
                    'choice_text' => $distractor,
                    'is_correct' => false,
                ]);
            }
        }
    }

    protected function seedGreetingsQuiz(): void
    {
        $level = Level::where('name', 'FSL Greetings')->first()
            ?? Level::where('order', 3)->first();

        if (!$level) {
            return;
        }

        if (Quiz::where('level_id', $level->id)->exists()) {
            return;
        }

        $quiz = Quiz::create([
            'level_id' => $level->id,
            'title' => 'FSL Greetings Quiz',
            'description' => 'Test your understanding of common greetings in Filipino Sign Language.',
            'is_active' => true,
        ]);

        $signs = Sign::where('level_id', $level->id)->orderBy('sort_order')->get();
        if ($signs->isEmpty()) {
            return;
        }

        $allGreetings = $signs->pluck('name')->filter()->values()->toArray();

        foreach ($signs as $sign) {
            $greetingName = $sign->name;

            $question = Question::create([
                'quiz_id' => $quiz->id,
                'sign_id' => $sign->id,
                'question_text' => 'What sign or expression is being shown?',
                'question_type' => 'mcq',
                'correct_answer' => $greetingName,
            ]);

            $distractors = collect($allGreetings)
                ->filter(fn($g) => $g !== $greetingName)
                ->shuffle()
                ->take(3)
                ->values();

            Choice::create([
                'question_id' => $question->id,
                'choice_text' => $greetingName,
                'is_correct' => true,
            ]);

            foreach ($distractors as $distractor) {
                Choice::create([
                    'question_id' => $question->id,
                    'choice_text' => $distractor,
                    'is_correct' => false,
                ]);
            }
        }
    }
}
