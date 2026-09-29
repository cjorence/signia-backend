<?php

namespace App\Services;

use App\Models\GestureLog;
use App\Models\Progress;
use App\Models\QuizAttempt;

class UserActivityService
{
    /**
     * Get combined user activities (webcam gesture practices, completed lessons, quiz attempts).
     *
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public function getUserActivities(int $userId, int $limit = 10): array
    {
        $activities = [];

        // 1. Gesture Practice Attempts
        $gestures = GestureLog::where('user_id', $userId)
            ->with('sign')
            ->latest()
            ->take($limit)
            ->get();

        foreach ($gestures as $log) {
            $signName = $log->sign?->name ?: ($log->expected_sign ? 'Sign ' . strtoupper($log->expected_sign) : 'Sign');
            $isCorrect = (bool) $log->is_correct;
            $conf = round((float) ($log->confidence ?? 0));

            $activities[] = [
                'id' => 'gesture_' . $log->id,
                'type' => 'gesture',
                'title' => "Practiced {$signName}",
                'status' => $isCorrect ? 'Success' : 'Needs Work',
                'accuracy' => "{$conf}% accuracy",
                'created_at' => $log->created_at?->toISOString() ?: now()->toISOString(),
                'timestamp' => $log->created_at?->timestamp ?? 0,
            ];
        }

        // 2. Lesson / Sign Completions
        $progressList = Progress::where('user_id', $userId)
            ->where('is_completed', true)
            ->with(['sign', 'level'])
            ->latest('updated_at')
            ->take($limit)
            ->get();

        foreach ($progressList as $prog) {
            $signName = $prog->sign?->name ?: 'Lesson';
            $levelName = $prog->level?->name ?: 'Lesson';
            $conf = (float) ($prog->best_confidence ?? 0);
            $accuracyLabel = $conf > 0 ? (round($conf) . '% best accuracy') : $levelName;

            $activities[] = [
                'id' => 'progress_' . $prog->id,
                'type' => 'lesson',
                'title' => "Completed {$signName}",
                'status' => 'Completed',
                'accuracy' => $accuracyLabel,
                'created_at' => ($prog->updated_at ?: $prog->created_at)?->toISOString() ?: now()->toISOString(),
                'timestamp' => ($prog->updated_at ?: $prog->created_at)?->timestamp ?? 0,
            ];
        }

        // 3. Quiz Attempts
        $quizAttempts = QuizAttempt::where('user_id', $userId)
            ->with(['quiz', 'question.sign'])
            ->latest()
            ->take($limit)
            ->get();

        foreach ($quizAttempts as $attempt) {
            $signName = $attempt->question?->sign?->name;
            $quizTitle = $attempt->quiz?->title ?: 'Quiz';
            $title = $signName ? "Quiz: {$signName}" : "Quiz: {$quizTitle}";
            $isPassed = (int) $attempt->score > 0;

            $activities[] = [
                'id' => 'quiz_' . $attempt->id,
                'type' => 'quiz',
                'title' => $title,
                'status' => $isPassed ? 'Passed' : 'Needs Review',
                'accuracy' => $isPassed ? 'Correct answer' : 'Practice more',
                'created_at' => $attempt->created_at?->toISOString() ?: now()->toISOString(),
                'timestamp' => $attempt->created_at?->timestamp ?? 0,
            ];
        }

        // Sort descending by timestamp
        usort($activities, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);

        return array_slice($activities, 0, $limit);
    }
}
