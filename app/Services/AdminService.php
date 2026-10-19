<?php

namespace App\Services;

use App\Models\AdminLog;
use App\Models\GestureLog;
use App\Models\Level;
use App\Models\Progress;
use App\Models\QuizAttempt;
use App\Models\Sign;
use App\Models\Story;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AdminService
{
    public function getUsers(): Collection
    {
        return User::with('playerProfile')
            ->withCount([
                'progress as signs_learned_count' => function ($query) {
                    $query->where('is_completed', true);
                },
                'storyProgress as stories_done_count' => function ($query) {
                    $query->where('status', 'completed');
                },
            ])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getUserDetail(User $user): User
    {
        return $user->load([
            'playerProfile',
            'progress.sign',
            'gestureLogs.sign',
            'scores',
            'quizAttempts.quiz',
        ]);
    }

    public function activateUser(User $admin, User $user): User
    {
        $user->update([
            'is_active' => true,
        ]);

        $this->logAction($admin, "Activated user ID {$user->id}");

        return $user->fresh()->load('playerProfile');
    }

    public function deactivateUser(User $admin, User $user): User
    {
        if ($admin->id === $user->id) {
            abort(400, 'You cannot deactivate your own account.');
        }

        DB::transaction(function () use ($admin, $user) {
            $user->update(['is_active' => false]);
            $user->tokens()->delete();
            $this->logAction($admin, "Deactivated user ID {$user->id}");
        });

        return $user->fresh()->load('playerProfile');
    }

    public function getAnalytics(): array
    {
        $totalUsers = User::where('role', 'user')->count();
        $activeUsers = User::where('role', 'user')
            ->where('is_active', true)
            ->count();

        $totalSigns = Sign::count();
        $totalLevels = Level::count();
        $totalStories = Story::count();

        $totalGestures = GestureLog::count();
        $correctGestures = GestureLog::where('is_correct', true)->count();

        $averageAccuracy = $totalGestures > 0
            ? round(($correctGestures / $totalGestures) * 100, 2)
            : 0.0;

        $mostFailedSigns = GestureLog::query()
            ->select('sign_id', DB::raw('COUNT(*) as failed_count'))
            ->where('is_correct', false)
            ->with('sign')
            ->groupBy('sign_id')
            ->orderByDesc('failed_count')
            ->limit(5)
            ->get();

        // Strategic Weekly Activity (last 7 days)
        $weeklyActivity = [];
        $dayCounts = [];
        $weekStart = Carbon::now()->subDays(6)->startOfDay();
        $weekEnd = Carbon::now()->endOfDay();
        $dailyCounts = [];
        foreach ([GestureLog::class, QuizAttempt::class, Progress::class] as $model) {
            $dailyCounts[] = $model::whereBetween('created_at', [$weekStart, $weekEnd])
                ->selectRaw('DATE(created_at) as activity_day, COUNT(*) as activity_count')
                ->groupByRaw('DATE(created_at)')
                ->pluck('activity_count', 'activity_day');
        }
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $day = $date->toDateString();
            $dayCount = array_sum(array_map(fn ($counts) => (int) ($counts[$day] ?? 0), $dailyCounts));

            $dayCounts[] = [
                'day' => strtoupper(substr($date->format('l'), 0, 1)),
                'day_name' => $date->format('D'),
                'count' => $dayCount,
            ];
        }

        $maxCount = max(array_column($dayCounts, 'count'));
        foreach ($dayCounts as $item) {
            $pct = $maxCount > 0 ? (int) round(($item['count'] / $maxCount) * 100) : 0;
            // Height in SVG is inverted: 20 (peak) to 85 (baseline)
            $height = $maxCount > 0 ? (int) round(85 - (($pct / 100) * 65)) : 75;
            $display = $maxCount > 0 ? "{$pct}%" : "0%";
            $weeklyActivity[] = [
                'day' => $item['day'],
                'day_name' => $item['day_name'],
                'height' => $height,
                'display' => $display,
                'count' => $item['count'],
            ];
        }

        // Most Active Lessons (ranked by practice volume and completions)
        $allLevels = Level::all();
        $progressByLevel = Progress::select('level_id')
            ->selectRaw('COUNT(*) as activity_count, COUNT(DISTINCT CASE WHEN is_completed = 1 THEN user_id END) as completed_users')
            ->groupBy('level_id')->get()->keyBy('level_id');
        $gesturesByLevel = GestureLog::select('level_id')->selectRaw('COUNT(*) as activity_count')
            ->groupBy('level_id')->pluck('activity_count', 'level_id');
        $quizzesByLevel = QuizAttempt::join('quizzes', 'quizzes.id', '=', 'quiz_attempts.quiz_id')
            ->select('quizzes.level_id')->selectRaw('COUNT(*) as activity_count')
            ->groupBy('quizzes.level_id')->pluck('activity_count', 'quizzes.level_id');
        $rankedLessons = [];
        foreach ($allLevels as $level) {
            $progressStats = $progressByLevel->get($level->id);
            $activityCount = (int) ($progressStats?->activity_count ?? 0)
                + (int) ($gesturesByLevel[$level->id] ?? 0)
                + (int) ($quizzesByLevel[$level->id] ?? 0);
            $completedUsers = (int) ($progressStats?->completed_users ?? 0);

            $completionRate = $totalUsers > 0
                ? min(100, (int) round(($completedUsers / $totalUsers) * 100))
                : 0;

            $rankedLessons[] = [
                'id' => $level->id,
                'order' => $level->order,
                'title' => "Level {$level->order} · {$level->name}",
                'activity_count' => $activityCount,
                'completion' => $completionRate,
            ];
        }

        // All lessons ordered by curriculum sequence
        $allLessons = $rankedLessons;
        usort($allLessons, fn($a, $b) => $a['order'] <=> $b['order']);

        // Most active lessons ranked by activity count descending
        $topLessons = $rankedLessons;
        usort($topLessons, function ($a, $b) {
            if ($b['activity_count'] === $a['activity_count']) {
                return $a['order'] <=> $b['order'];
            }
            return $b['activity_count'] <=> $a['activity_count'];
        });
        $topLessons = array_slice($topLessons, 0, 5);

        // Story Quests Performance (top 3 stories)
        $storyQuests = [];
        $stories = Story::withCount([
            'progress as plays_count',
            'progress as completions_count' => fn ($query) => $query->where('status', 'completed'),
        ])->orderBy('chapter_number')->take(3)->get();
        foreach ($stories as $story) {
            $plays = (int) $story->plays_count;
            $completions = (int) $story->completions_count;
            $storyCompletion = $plays > 0
                ? (int) round(($completions / $plays) * 100)
                : 0;

            $storyQuests[] = [
                'title' => $story->title,
                'plays' => $plays,
                'completion' => $storyCompletion,
            ];
        }

        // Recent System Activities
        $recentActivities = [];

        // 1. Users
        $recentUsers = User::where('role', 'user')->latest()->take(3)->get();
        foreach ($recentUsers as $u) {
            $recentActivities[] = [
                'id' => 'user_' . $u->id,
                'type' => 'user',
                'text' => "New user registered: {$u->email}",
                'time' => $u->created_at ? $u->created_at->diffForHumans() : 'Recently',
                'timestamp' => $u->created_at ? $u->created_at->timestamp : 0,
            ];
        }

        // 2. Sign completions
        $recentProgress = Progress::where('is_completed', true)
            ->with(['user', 'sign'])
            ->latest('updated_at')
            ->take(3)
            ->get();
        foreach ($recentProgress as $p) {
            $signName = $p->sign?->name ?? 'Sign';
            $userName = $p->user?->name ?? 'User';
            $recentActivities[] = [
                'id' => 'progress_' . $p->id,
                'type' => 'sign',
                'text' => "Sign '{$signName}' completed by {$userName}",
                'time' => $p->updated_at ? $p->updated_at->diffForHumans() : 'Recently',
                'timestamp' => $p->updated_at ? $p->updated_at->timestamp : 0,
            ];
        }

        // 3. Quiz Milestones
        $recentQuizzes = QuizAttempt::with(['user', 'quiz'])->latest()->take(3)->get();
        foreach ($recentQuizzes as $q) {
            $quizTitle = $q->quiz?->title ?? 'Quiz';
            $userName = $q->user?->name ?? 'User';
            $recentActivities[] = [
                'id' => 'quiz_' . $q->id,
                'type' => 'milestone',
                'text' => "{$userName} scored {$q->score}% in {$quizTitle}",
                'time' => $q->created_at ? $q->created_at->diffForHumans() : 'Recently',
                'timestamp' => $q->created_at ? $q->created_at->timestamp : 0,
            ];
        }

        // 4. Admin logs
        $recentLogs = AdminLog::with('admin')->latest()->take(3)->get();
        foreach ($recentLogs as $l) {
            $recentActivities[] = [
                'id' => 'admin_' . $l->id,
                'type' => 'model',
                'text' => $l->action,
                'time' => $l->created_at ? $l->created_at->diffForHumans() : 'Recently',
                'timestamp' => $l->created_at ? $l->created_at->timestamp : 0,
            ];
        }

        // Sort descending by timestamp
        usort($recentActivities, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);
        $recentActivities = array_slice($recentActivities, 0, 5);

        return [
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'total_signs' => $totalSigns,
            'total_levels' => $totalLevels,
            'total_stories' => $totalStories,
            'average_accuracy' => $averageAccuracy,
            'most_failed_signs' => $mostFailedSigns,
            'lesson_difficulty' => $this->getLessonDifficulty(),
            'weekly_activity' => $weeklyActivity,
            'top_lessons' => $topLessons,
            'all_lessons' => $allLessons,
            'story_performance' => $storyQuests,
            'recent_activities' => $recentActivities,
        ];
    }

    private function getLessonDifficulty(): array
    {
        $end = Carbon::now();
        $minimumAttempts = 10;
        $minimumPlayers = 3;

        // Only per-question submissions have a known denominator and a binary score.
        // Whole-quiz scores do not retain the historical number of questions answered.
        $lessons = Level::query()
            ->join('quizzes', 'quizzes.level_id', '=', 'levels.id')
            ->join('quiz_attempts', 'quiz_attempts.quiz_id', '=', 'quizzes.id')
            ->join('users', 'users.id', '=', 'quiz_attempts.user_id')
            ->where('users.role', 'user')
            ->whereNotNull('quiz_attempts.question_id')
            ->whereIn('quiz_attempts.score', [0, 1])
            ->whereBetween('quiz_attempts.completed_at', [$end->copy()->subDays(30), $end])
            ->select('levels.id', 'levels.name', 'levels.order')
            ->selectRaw('COUNT(*) as attempt_count, COUNT(DISTINCT quiz_attempts.user_id) as player_count')
            ->selectRaw('SUM(CASE WHEN quiz_attempts.score = 0 THEN 1 ELSE 0 END) as incorrect_count')
            ->groupBy('levels.id', 'levels.name', 'levels.order')
            ->get()
            ->map(function ($level) use ($minimumAttempts, $minimumPlayers) {
                $attempts = (int) $level->attempt_count;
                $players = (int) $level->player_count;
                $sufficient = $attempts >= $minimumAttempts && $players >= $minimumPlayers;

                return [
                    'id' => $level->id,
                    'title' => "Level {$level->order} · {$level->name}",
                    'attempt_count' => $attempts,
                    'player_count' => $players,
                    'incorrect_count' => (int) $level->incorrect_count,
                    'incorrect_rate' => $sufficient ? round($level->incorrect_count / $attempts * 100, 1) : null,
                    'has_enough_data' => $sufficient,
                ];
            })
            ->sort(function ($a, $b) {
                return ($b['has_enough_data'] <=> $a['has_enough_data'])
                    ?: (($b['incorrect_rate'] ?? 0) <=> ($a['incorrect_rate'] ?? 0))
                    ?: ($b['attempt_count'] <=> $a['attempt_count'])
                    ?: ($a['id'] <=> $b['id']);
            })
            ->take(5)
            ->values()
            ->all();

        return [
            'window_days' => 30,
            'minimum_attempts' => $minimumAttempts,
            'minimum_players' => $minimumPlayers,
            'lessons' => $lessons,
        ];
    }

    public function getAdminLogs(): Collection
    {
        return AdminLog::with('admin')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function logAction(User $admin, string $action): AdminLog
    {
        return AdminLog::create([
            'admin_id' => $admin->id,
            'action' => $action,
        ]);
    }
}
