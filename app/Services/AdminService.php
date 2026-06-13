<?php

namespace App\Services;

use App\Models\AdminLog;
use App\Models\GestureLog;
use App\Models\User;
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

        $activeUsers = User::where('role', 'user')
            ->where('is_active', true)
            ->count();

        return [
            'total_users' => $totalUsers,
            'average_accuracy' => $averageAccuracy,
            'most_failed_signs' => $mostFailedSigns,
            'active_users' => $activeUsers,
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