<?php

namespace App\Services;

use App\Models\Level;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FinisherService
{
    /**
     * Get finishers for a specific level or across all levels.
     *
     * @param int|null $levelId
     * @param int $limit
     * @return array
     */
    public function getFinishers(?int $levelId = null, int $limit = 50): array
    {
        if ($levelId !== null && $levelId > 0) {
            return $this->getFinishersForLevel($levelId, $limit);
        }

        return $this->getAllCategoryFinishers($limit);
    }

    /**
     * Get finishers who completed 100% of signs in a specific level.
     */
    protected function getFinishersForLevel(int $levelId, int $limit): array
    {
        $level = Level::withCount('signs')->find($levelId);
        if (!$level || $level->signs_count === 0) {
            return [];
        }

        $totalSigns = $level->signs_count;

        $rows = DB::table('progress')
            ->join('users', 'users.id', '=', 'progress.user_id')
            ->leftJoin('player_profiles', 'player_profiles.user_id', '=', 'users.id')
            ->where('progress.level_id', $levelId)
            ->where('progress.is_completed', true)
            ->groupBy('users.id', 'users.name', 'player_profiles.avatar', 'player_profiles.total_xp', 'player_profiles.streak')
            ->havingRaw('COUNT(DISTINCT progress.sign_id) >= ?', [$totalSigns])
            ->select([
                'users.id as user_id',
                'users.name',
                'player_profiles.avatar',
                DB::raw('COALESCE(player_profiles.total_xp, 0) as total_xp'),
                DB::raw('COALESCE(player_profiles.streak, 0) as streak'),
                DB::raw('MAX(progress.updated_at) as finished_at'),
            ])
            ->orderByDesc('total_xp')
            ->orderBy('finished_at', 'asc')
            ->limit($limit)
            ->get();

        return $rows->map(function ($row) use ($level) {
            return [
                'user_id' => (int) $row->user_id,
                'name' => $row->name,
                'avatar' => $row->avatar ?: '👤',
                'xp' => (int) $row->total_xp,
                'streak' => (int) $row->streak,
                'info' => 'Sign Finisher',
                'finished_at' => $row->finished_at,
                'level_id' => $level->id,
                'level_name' => $level->name,
                'completed_categories_count' => 1,
            ];
        })->all();
    }

    /**
     * Get finishers across all categories, ranked by categories completed and total XP.
     */
    protected function getAllCategoryFinishers(int $limit): array
    {
        $levels = Level::has('signs')
            ->withCount('signs')
            ->get()
            ->keyBy('id');

        if ($levels->isEmpty()) {
            return [];
        }

        // Query completion counts per user per level
        $records = DB::table('progress')
            ->where('is_completed', true)
            ->whereIn('level_id', $levels->keys())
            ->groupBy('user_id', 'level_id')
            ->select([
                'user_id',
                'level_id',
                DB::raw('COUNT(DISTINCT sign_id) as completed_count'),
                DB::raw('MAX(updated_at) as finished_at'),
            ])
            ->get();

        $userCompleted = [];

        foreach ($records as $record) {
            $level = $levels->get($record->level_id);
            if ($level && $record->completed_count >= $level->signs_count) {
                $uid = (int) $record->user_id;
                if (!isset($userCompleted[$uid])) {
                    $userCompleted[$uid] = [
                        'completed_level_ids' => [],
                        'latest_finished_at' => $record->finished_at,
                    ];
                }
                $userCompleted[$uid]['completed_level_ids'][] = (int) $record->level_id;
                if ($record->finished_at > $userCompleted[$uid]['latest_finished_at']) {
                    $userCompleted[$uid]['latest_finished_at'] = $record->finished_at;
                }
            }
        }

        if (empty($userCompleted)) {
            return [];
        }

        $userIds = array_keys($userCompleted);
        $users = User::whereIn('id', $userIds)
            ->with('playerProfile')
            ->get();

        $finishers = [];

        foreach ($users as $user) {
            $completionData = $userCompleted[$user->id] ?? null;
            if (!$completionData) {
                continue;
            }

            $count = count($completionData['completed_level_ids']);
            $xp = (int) ($user->playerProfile?->total_xp ?? 0);
            $catText = $count === 1 ? '1 category' : "{$count} categories";

            $finishers[] = [
                'user_id' => $user->id,
                'name' => $user->name,
                'avatar' => $user->playerProfile?->avatar ?: '👤',
                'xp' => $xp,
                'streak' => (int) ($user->playerProfile?->streak ?? 0),
                'info' => "Completed {$catText}",
                'finished_at' => $completionData['latest_finished_at'],
                'completed_categories_count' => $count,
                'completed_level_ids' => $completionData['completed_level_ids'],
            ];
        }

        // Sort by categories completed descending, then XP descending, then finished_at ascending
        usort($finishers, function ($a, $b) {
            if ($a['completed_categories_count'] !== $b['completed_categories_count']) {
                return $b['completed_categories_count'] <=> $a['completed_categories_count'];
            }
            if ($a['xp'] !== $b['xp']) {
                return $b['xp'] <=> $a['xp'];
            }
            return strcmp((string) $a['finished_at'], (string) $b['finished_at']);
        });

        return array_slice($finishers, 0, $limit);
    }
}
