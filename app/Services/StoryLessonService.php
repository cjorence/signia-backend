<?php

namespace App\Services;

use App\Models\Level;
use App\Models\Progress;
use App\Models\Story;
use App\Models\User;
use Illuminate\Support\Collection;

class StoryLessonService
{
    public function levels(): Collection
    {
        return Level::with(['signs:id,level_id'])->orderBy('order')->get();
    }

    public function idsFor(Story $story, Collection $levels): array
    {
        if ($story->recommended_level_ids !== null) {
            return array_map('intval', $story->recommended_level_ids);
        }

        $names = config('story_lessons.'.$story->slug, []);
        return $levels->filter(fn ($level) => in_array($level->name, $names, true))
            ->pluck('id')->all();
    }

    public function completedSigns(?User $user): array
    {
        return $user ? Progress::where('user_id', $user->id)->where('is_completed', true)
            ->pluck('sign_id')->all() : [];
    }

    public function forStory(Story $story, Collection $levels, array $completedSigns): array
    {
        $ids = $this->idsFor($story, $levels);

        return $levels->whereIn('id', $ids)->map(function ($level) use ($completedSigns) {
            $signIds = $level->signs->pluck('id')->all();
            $total = count($signIds);
            $completed = count(array_intersect($signIds, $completedSigns));

            return [
                'id' => $level->id,
                'title' => str_replace('_', ' ', $level->name),
                'total_lessons' => $total,
                'completed_lessons' => $completed,
                'is_completed' => $total > 0 && $completed === $total,
            ];
        })->values()->all();
    }
}
