<?php

namespace App\Services;

use App\Models\Choice;
use App\Models\Level;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Sign;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuizService
{
    public function ensureQuestionsForLevelSigns(Level $level): void
    {
        $quiz = $level->quizzes()->where('is_active', true)->first()
            ?? $level->quizzes()->first();

        if (! $quiz) {
            $quiz = Quiz::create([
                'level_id' => $level->id,
                'title' => "{$level->name} Quiz",
                'description' => "Practice quiz for {$level->name}",
                'is_active' => true,
            ]);
        }

        $signs = $level->signs()->get();
        if ($signs->isEmpty()) {
            return;
        }

        $existingSignIds = Question::where('quiz_id', $quiz->id)
            ->whereNotNull('sign_id')
            ->pluck('sign_id')
            ->all();

        $isAlphabet = str_contains(strtolower($quiz->title), 'alphabet')
            || str_contains(strtolower($level->name), 'alphabet');
        $questionText = $isAlphabet ? 'What alphabet letter is shown?' : 'What sign is shown?';

        foreach ($signs as $sign) {
            if (! in_array($sign->id, $existingSignIds)) {
                $letter = $sign->fsl_name ?: $sign->name;

                $question = Question::create([
                    'quiz_id' => $quiz->id,
                    'sign_id' => $sign->id,
                    'question_text' => $questionText,
                    'question_type' => 'mcq',
                    'correct_answer' => $letter,
                ]);

                Choice::create([
                    'question_id' => $question->id,
                    'choice_text' => $letter,
                    'is_correct' => true,
                ]);
            }
        }
    }

    public function getActiveQuizzesByLevel(Level $level): Collection
    {
        $this->ensureQuestionsForLevelSigns($level);

        $quizzes = $level->quizzes()
            ->where('is_active', true)
            ->with(['questions.choices', 'questions.sign'])
            ->withCount('questions')
            ->orderBy('id')
            ->get();

        return $this->attachLessonOptions($quizzes, $level);
    }

    public function getQuizForPlayer(Quiz $quiz): Quiz
    {
        if (! $quiz->is_active) {
            throw ValidationException::withMessages([
                'quiz' => 'This quiz is not active.',
            ]);
        }

        $quiz->load(['level', 'questions.choices', 'questions.sign']);

        return $this->attachLessonOptions(collect([$quiz]), $quiz->level)->first();
    }

    public function getQuizForAdmin(Quiz $quiz): Quiz
    {
        return $quiz->load(['level', 'questions.choices', 'questions.sign']);
    }

    public function createQuiz(array $data): Quiz
    {
        return Quiz::create($data)->load('level');
    }

    public function updateQuiz(Quiz $quiz, array $data): Quiz
    {
        $quiz->update($data);

        return $quiz->fresh()->load('level');
    }

    public function deleteQuiz(Quiz $quiz): bool
    {
        return (bool) $quiz->delete();
    }

    public function addQuestion(Quiz $quiz, array $data): Question
    {
        $this->ensureQuestionSignMatchesLevel($quiz, $data['sign_id'] ?? null);

        $question = $quiz->questions()->create($data);

        return $question->load(['choices', 'sign']);
    }

    public function updateQuestion(Question $question, array $data): Question
    {
        $this->ensureQuestionSignMatchesLevel(
            $question->quiz,
            $data['sign_id'] ?? $question->sign_id
        );

        $question->update($data);

        return $question->fresh()->load(['choices', 'sign']);
    }

    public function deleteQuestion(Question $question): bool
    {
        return (bool) $question->delete();
    }

    public function addChoice(Question $question, array $data): Choice
    {
        return $question->choices()->create($data);
    }

    public function updateChoice(Choice $choice, array $data): Choice
    {
        $choice->update($data);

        return $choice->fresh();
    }

    public function deleteChoice(Choice $choice): bool
    {
        return (bool) $choice->delete();
    }

    public function submitQuiz(int $userId, Quiz $quiz, array $data): array
{
    if (! $quiz->is_active) {
        throw ValidationException::withMessages([
            'quiz' => 'Inactive quizzes cannot be submitted.',
        ]);
    }

    $user = User::findOrFail($userId);

    $this->heartService->ensureCanAttempt($user);

    $quiz->load('questions.choices');
    $questions = $quiz->questions->keyBy('id');
    $answers = collect($data['answers']);

    if ($answers->pluck('question_id')->duplicates()->isNotEmpty()) {
        throw ValidationException::withMessages([
            'answers' => 'Each question can only be answered once.',
        ]);
    }

    $answers->each(function (array $answer) use ($questions): void {
        if (! $questions->has((int) $answer['question_id'])) {
            throw ValidationException::withMessages([
                'answers' => 'One or more submitted questions do not belong to this quiz.',
            ]);
        }
    });

    $score = $answers->reduce(function (int $score, array $answer) use ($questions): int {
        $question = $questions->get((int) $answer['question_id']);

        return $this->isCorrectAnswer($question, $answer['answer'], 'answers')
            ? $score + 1
            : $score;
    }, 0);

    $wrongAnswers = max($answers->count() - $score, 0);

    $attempt = DB::transaction(function () use ($user, $quiz, $score, $wrongAnswers, $questions) {
        if ($wrongAnswers > 0) {
            $this->heartService->deduct($user, $wrongAnswers, 'wrong_quiz_answer', [
                'quiz_id' => $quiz->id,
                'score' => $score,
                'total_questions' => $questions->count(),
            ]);
        }

        return QuizAttempt::create([
            'user_id' => $user->id,
            'quiz_id' => $quiz->id,
            'score' => $score,
            'completed_at' => now(),
        ]);
    });

    $this->progressService->recordDailyStreak($user->id);

    return [
        'attempt' => $attempt->load('quiz'),
        'score' => $score,
        'total_questions' => $questions->count(),
        'wrong_answers' => $wrongAnswers,
    ];
}

    public function submitQuestionAttempt(
        int $userId,
        Quiz $quiz,
        Question $question,
        mixed $answer = null,
        ?int $answerSignId = null
    ): array
    {
        if (! $quiz->is_active) {
            throw ValidationException::withMessages([
                'quiz' => 'Inactive quizzes cannot be submitted.',
            ]);
        }

        if ($question->quiz_id !== $quiz->id) {
            throw ValidationException::withMessages([
                'question' => 'The selected question does not belong to this quiz.',
            ]);
        }

        $question->loadMissing('sign');

        if ($question->sign_id && $question->sign?->level_id !== $quiz->level_id) {
            throw ValidationException::withMessages([
                'question' => 'The selected question is not configured for this quiz level.',
            ]);
        }

        $quiz->loadMissing('level');
        $user = User::findOrFail($userId);
        $this->heartService->ensureCanAttempt($user);

        if ($question->sign_id) {
            $lessonOptions = $this->lessonOptionsForQuestion($question, $quiz->level);
            $isEligibleOption = collect($lessonOptions)
                ->contains(fn (array $option) => $option['sign_id'] === $answerSignId);

            if (! $isEligibleOption) {
                throw ValidationException::withMessages([
                    'answer_sign_id' => 'The selected sign is not an available answer for this lesson.',
                ]);
            }

            $isCorrect = $answerSignId === $question->sign_id;
        } else {
            $question->loadMissing('choices');
            $isCorrect = $this->isCorrectAnswer($question, $answer);
        }
        $score = $isCorrect ? 1 : 0;
        $wrongAnswers = $isCorrect ? 0 : 1;

        $attempt = DB::transaction(function () use ($user, $quiz, $question, $score, $wrongAnswers, $answerSignId) {
            if ($wrongAnswers > 0) {
                $this->heartService->deduct($user, 1, 'wrong_quiz_answer', [
                    'quiz_id' => $quiz->id,
                    'question_id' => $question->id,
                    'score' => $score,
                    'total_questions' => 1,
                ]);
            }

            return QuizAttempt::create([
                'user_id' => $user->id,
                'quiz_id' => $quiz->id,
                'question_id' => $question->id,
                'answer_sign_id' => $answerSignId,
                'score' => $score,
                'completed_at' => now(),
            ]);
        });

        if ($question->sign_id) {
            $this->progressService->evaluateCompletion($userId, $question->sign_id);
        }

        $this->progressService->recordDailyStreak($userId);

        return [
            'attempt' => $attempt->load(['quiz', 'question']),
            'is_correct' => $isCorrect,
            'score' => $score,
            'total_questions' => 1,
            'wrong_answers' => $wrongAnswers,
        ];
    }

    protected function isCorrectAnswer(Question $question, mixed $answer, string $errorKey = 'answer'): bool
    {
        if ($question->question_type === 'mcq') {
            $choice = $question->choices->firstWhere('id', (int) $answer);

            if (! $choice) {
                throw ValidationException::withMessages([
                    $errorKey => 'The selected choice is invalid or does not belong to the submitted question.',
                ]);
            }

            return $choice->is_correct;
        }

        return strtolower(trim((string) $answer)) === strtolower(trim($question->correct_answer));
    }

    private function ensureQuestionSignMatchesLevel(Quiz $quiz, ?int $signId): void
    {
        if ($signId === null) {
            return;
        }

        $sign = Sign::findOrFail($signId);

        if ($sign->level_id !== $quiz->level_id) {
            throw ValidationException::withMessages([
                'sign_id' => 'The selected sign must belong to the quiz level.',
            ]);
        }
    }

    private function attachLessonOptions(\Illuminate\Support\Collection|Collection $quizzes, Level $level): \Illuminate\Support\Collection|Collection
    {
        $orderedSigns = $level->signs()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name', 'fsl_name']);

        foreach ($quizzes as $quiz) {
            foreach ($quiz->questions as $question) {
                $question->setAttribute('lesson_options', $this->lessonOptionsForQuestion($question, $orderedSigns));
            }
        }

        return $quizzes;
    }

    /**
     * Player lesson options come from the ordered signs in the same category.
     * The first four signs share the category's first four answers. Later
     * signs use themselves plus three deterministic pseudo-random earlier signs.
     * This keeps a displayed option valid when the player submits it; the
     * frontend is responsible for shuffling the visual order per attempt.
     */
    private function lessonOptionsForQuestion(
        Question $question,
        Level|\Illuminate\Support\Collection|Collection $levelOrSigns
    ): array
    {
        if (! $question->sign_id) {
            return [];
        }

        $orderedSigns = $levelOrSigns instanceof Level
            ? $levelOrSigns->signs()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'name', 'fsl_name'])
            : $levelOrSigns;

        $currentIndex = $orderedSigns->search(
            fn (Sign $sign) => $sign->id === $question->sign_id
        );

        if ($currentIndex === false) {
            return [];
        }

        $currentSign = $orderedSigns->get($currentIndex);
        $options = $currentIndex < 4
            ? $orderedSigns->take(4)
            : collect([$currentSign])->merge(
                $orderedSigns
                    ->take($currentIndex)
                    ->sortBy(fn (Sign $sign) => crc32("{$question->id}:{$sign->id}"))
                    ->take(3)
            );

        return $options
            ->map(fn (Sign $sign) => [
                'sign_id' => $sign->id,
                'label' => $sign->fsl_name ?: $sign->name,
            ])
            ->values()
            ->all();
    }

    public function __construct(
        protected HeartService $heartService,
        protected ProgressService $progressService
    ) {}
}
