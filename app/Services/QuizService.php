<?php

namespace App\Services;

use App\Models\Choice;
use App\Models\Level;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuizService
{
    public function getActiveQuizzesByLevel(Level $level): Collection
    {
        return $level->quizzes()
            ->where('is_active', true)
            ->withCount('questions')
            ->orderBy('id')
            ->get();
    }

    public function getQuizForPlayer(Quiz $quiz): Quiz
    {
        if (! $quiz->is_active) {
            throw ValidationException::withMessages([
                'quiz' => 'This quiz is not active.',
            ]);
        }

        return $quiz->load(['level', 'questions.choices', 'questions.sign']);
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
        $question = $quiz->questions()->create($data);

        return $question->load(['choices', 'sign']);
    }

    public function updateQuestion(Question $question, array $data): Question
    {
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

        return $this->isCorrectAnswer($question, $answer['answer'])
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

    return [
        'attempt' => $attempt->load('quiz'),
        'score' => $score,
        'total_questions' => $questions->count(),
        'wrong_answers' => $wrongAnswers,
    ];
}

    protected function isCorrectAnswer(Question $question, mixed $answer): bool
    {
        if ($question->question_type === 'mcq') {
            $choice = $question->choices->firstWhere('id', (int) $answer);

            if (! $choice) {
                throw ValidationException::withMessages([
                    'answers' => 'One or more selected choices do not belong to the submitted question.',
                ]);
            }

            return $choice->is_correct;
        }

        return strtolower(trim((string) $answer)) === strtolower(trim($question->correct_answer));
    }

    public function __construct(
        protected HeartService $heartService
    ) {}
}
