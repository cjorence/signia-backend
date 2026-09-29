<?php

namespace App\Services;

use App\Models\AdminLog;
use App\Models\GestureLog;
use App\Models\Level;
use App\Models\Progress;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Sign;
use App\Models\Story;
use App\Models\User;
use App\Models\UserStoryProgress;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AdminService
{
    public function getUsers(): Collection
    {
        return User::with('playerProfile')
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
        $user->update([
            'is_active' => false,
        ]);

        $this->logAction($admin, "Deactivated user ID {$user->id}");

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
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $startOfDay = $date->copy()->startOfDay();
            $endOfDay = $date->copy()->endOfDay();

            $gesturesCount = GestureLog::whereBetween('created_at', [$startOfDay, $endOfDay])->count();
            $quizzesCount = QuizAttempt::whereBetween('created_at', [$startOfDay, $endOfDay])->count();
            $progressCount = Progress::whereBetween('created_at', [$startOfDay, $endOfDay])->count();
            $dayCount = $gesturesCount + $quizzesCount + $progressCount;

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
        $rankedLessons = [];
        foreach ($allLevels as $level) {
            $quizIds = Quiz::where('level_id', $level->id)->pluck('id');
            $progressCount = Progress::where('level_id', $level->id)->count();
            $gesturesCount = GestureLog::where('level_id', $level->id)->count();
            $quizzesCount = $quizIds->isNotEmpty()
                ? QuizAttempt::whereIn('quiz_id', $quizIds)->count()
                : 0;

            $activityCount = $progressCount + $gesturesCount + $quizzesCount;

            $completedUsers = Progress::where('level_id', $level->id)
                ->where('is_completed', true)
                ->distinct('user_id')
                ->count('user_id');

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
        $stories = Story::orderBy('chapter_number')->take(3)->get();
        foreach ($stories as $story) {
            $plays = UserStoryProgress::where('story_id', $story->id)->count();
            $completions = UserStoryProgress::where('story_id', $story->id)
                ->where('status', 'completed')
                ->count();
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
            'weekly_activity' => $weeklyActivity,
            'top_lessons' => $topLessons,
            'all_lessons' => $allLessons,
            'story_performance' => $storyQuests,
            'recent_activities' => $recentActivities,
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