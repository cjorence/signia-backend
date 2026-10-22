<?php

namespace App\Services;

use App\Models\GestureLog;
use App\Models\Progress;
use App\Models\QuizAttempt;
use App\Models\UserAchievement;
use App\Models\UserStoryProgress;

class UserActivityService
{
    /**
     * Get combined user activities (achievements, story quests, completed lessons, quiz milestones, gesture practices).
     *
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public function getUserActivities(int $userId, int $limit = 10): array
    {
        $activities = [];

        // 1. Unlocked Achievements (High-value milestone)
        $userAchievements = UserAchievement::where('user_id', $userId)
            ->with('achievement')
            ->latest('unlocked_at')
            ->take($limit)
            ->get();

        foreach ($userAchievements as $ua) {
            $achName = $ua->achievement?->name ?: 'Achievement';
            $activities[] = [
                'id' => 'achievement_' . $ua->id,
                'type' => 'achievement',
                'title' => "Unlocked {$achName}",
                'status' => 'Success',
                'accuracy' => 'Badge Unlocked',
                'created_at' => ($ua->unlocked_at ?: $ua->created_at)?->toISOString() ?: now()->toISOString(),
                'timestamp' => ($ua->unlocked_at ?: $ua->created_at)?->timestamp ?? 0,
            ];
        }

        // 2. Story Quest Completions (Narrative milestone)
        $storyCompletions = UserStoryProgress::where('user_id', $userId)
            ->where('status', 'completed')
            ->with('story')
            ->latest('completed_at')
            ->take($limit)
            ->get();

        foreach ($storyCompletions as $sc) {
            $storyTitle = $sc->story?->title ?: 'Story Quest';
            $activities[] = [
                'id' => 'story_' . $sc->id,
                'type' => 'story',
                'title' => "Completed Story: {$storyTitle}",
                'status' => 'Completed',
                'accuracy' => 'Story Quest',
                'created_at' => ($sc->completed_at ?: $sc->created_at)?->toISOString() ?: now()->toISOString(),
                'timestamp' => ($sc->completed_at ?: $sc->created_at)?->timestamp ?? 0,
            ];
        }

        // 3. Lesson / Sign Completions (Core progression)
        $progressList = Progress::where('user_id', $userId)
            ->where('is_completed', true)
            ->with(['sign', 'level'])
            ->latest('updated_at')
            ->take($limit)
            ->get();

        $completedSignNames = [];
        foreach ($progressList as $prog) {
            $signName = $prog->sign?->name ?: 'Lesson';
            $levelName = $prog->level?->name ?: 'Lesson';
            $conf = (float) ($prog->best_confidence ?? 0);
            $accuracyLabel = $conf > 0 ? (round($conf) . '% best accuracy') : $levelName;

            $completedSignNames[strtolower(trim($signName))] = true;

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

        // 4. Quiz Milestones
        // Prefer whole-quiz attempts (where question_id IS NULL).
        // If whole-quiz attempts exist, record them. If only per-question attempts exist,
        // deduplicate by quiz / sign to avoid spamming multiple identical questions.
        $wholeQuizAttempts = QuizAttempt::where('user_id', $userId)
            ->whereNull('question_id')
            ->with('quiz')
            ->latest('completed_at')
            ->take($limit)
            ->get();

        if ($wholeQuizAttempts->isNotEmpty()) {
            foreach ($wholeQuizAttempts as $attempt) {
                $quizTitle = $attempt->quiz?->title ?: 'Quiz';
                $score = (int) $attempt->score;
                $isPassed = $score > 0;

                $activities[] = [
                    'id' => 'quiz_' . $attempt->id,
                    'type' => 'quiz',
                    'title' => "Quiz: {$quizTitle}",
                    'status' => $isPassed ? 'Passed' : 'Needs Review',
                    'accuracy' => $isPassed ? "Scored {$score} pts" : 'Practice more',
                    'created_at' => ($attempt->completed_at ?: $attempt->created_at)?->toISOString() ?: now()->toISOString(),
                    'timestamp' => ($attempt->completed_at ?: $attempt->created_at)?->timestamp ?? 0,
                ];
            }
        } else {
            // Fallback for per-question quiz attempts: deduplicate by sign to prevent repeating "Quiz: Letter E"
            $questionAttempts = QuizAttempt::where('user_id', $userId)
                ->whereNotNull('question_id')
                ->with(['quiz', 'question.sign'])
                ->latest()
                ->take($limit * 2)
                ->get();

            $seenSigns = [];
            foreach ($questionAttempts as $attempt) {
                $signName = $attempt->question?->sign?->name;
                $normalizedSign = $signName ? strtolower(trim($signName)) : null;

                // Skip if we already added a quiz entry for this sign or if the sign is already marked "Completed" in progress
                if ($normalizedSign && (isset($seenSigns[$normalizedSign]) || isset($completedSignNames[$normalizedSign]))) {
                    continue;
                }
                if ($normalizedSign) {
                    $seenSigns[$normalizedSign] = true;
                }

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

                if (count($seenSigns) >= $limit) {
                    break;
                }
            }
        }

        // 5. Gesture Practice Attempts (Webcam practice)
        // Deduplicate: if the user already has "Completed [Sign]" in recent progress, skip the redundant raw practice rows
        $gestures = GestureLog::where('user_id', $userId)
            ->with('sign')
            ->latest()
            ->take($limit * 2)
            ->get();

        $seenPracticedSigns = [];
        foreach ($gestures as $log) {
            $signName = $log->sign?->name ?: ($log->expected_sign ? 'Sign ' . strtoupper($log->expected_sign) : 'Sign');
            $normalizedSign = strtolower(trim($signName));

            // Avoid redundant practice row if already shown or already completed in this batch
            if (isset($seenPracticedSigns[$normalizedSign]) || isset($completedSignNames[$normalizedSign])) {
                continue;
            }
            $seenPracticedSigns[$normalizedSign] = true;

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

            if (count($seenPracticedSigns) >= 3) {
                break;
            }
        }

        // Sort descending by timestamp
        usort($activities, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);

        return array_slice($activities, 0, $limit);
    }
}

