<?php

namespace App\Services;

use App\Models\Purchase;
use App\Models\Story;
use App\Models\User;
use App\Models\UserStoryProgress;
use App\Models\UserStoryUnlock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StoryService
{
    public function getStoriesForUser(?User $user): Collection
    {
        $stories = Story::orderBy('chapter_number')->orderBy('order')->get();

        $unlockedStoryIds = [];
        $completedStoryIds = [];

        if ($user) {
            $unlockedStoryIds = UserStoryUnlock::where('user_id', $user->id)
                ->pluck('story_id')
                ->all();

            $completedStoryIds = UserStoryProgress::where('user_id', $user->id)
                ->where('status', 'completed')
                ->pluck('story_id')
                ->all();
        }

        return $stories->map(function (Story $story) use ($user, $unlockedStoryIds, $completedStoryIds) {
            $isFree = (bool) $story->is_free || $story->chapter_number === 1;
            $isAdmin = $user && $user->role === 'admin';
            $isUnlocked = $isFree || $isAdmin || in_array($story->id, $unlockedStoryIds, true);
            $isCompleted = in_array($story->id, $completedStoryIds, true);

            $status = $isCompleted ? 'complete' : ($isUnlocked ? 'continue' : 'locked');

            return [
                'id' => $story->id,
                'slug' => $story->slug,
                'title' => $story->title,
                'description' => $story->description,
                'chapter_number' => $story->chapter_number,
                'order' => $story->order,
                'is_free' => $isFree,
                'price' => (float) $story->price,
                'currency' => $story->currency ?? 'PHP',
                'episodes' => $story->episodes,
                'completed' => $isCompleted ? $story->episodes : 0,
                'icon' => $story->icon,
                'color' => $story->color,
                'cover_url' => $story->cover_url,
                'file_name' => $story->file_name,
                'required_lesson_ids' => $story->required_lesson_ids ?? [],
                'is_unlocked' => $isUnlocked,
                'is_completed' => $isCompleted,
                'status' => $status,
                'can_unlock_with_payment' => ! $isUnlocked && ! $isFree,
            ];
        });
    }

    public function createStoryPurchase(User $user, Story $story): Purchase
    {
        if ($story->is_free || $story->chapter_number === 1) {
            $this->unlockForUser($user, $story);

            throw ValidationException::withMessages([
                'story' => 'Chapter 1 is free and has already been unlocked for your account.',
            ]);
        }

        if ($story->isUnlockedFor($user)) {
            throw ValidationException::withMessages([
                'story' => 'You have already unlocked this chapter.',
            ]);
        }

        return Purchase::create([
            'user_id' => $user->id,
            'product_type' => 'story',
            'story_id' => $story->id,
            'package_key' => 'story_chapter_'.$story->chapter_number,
            'quantity' => 1,
            'amount' => $story->price,
            'currency' => $story->currency ?? 'PHP',
            'status' => 'pending',
        ])->load(['user', 'story']);
    }

    public function unlockForUser(User $user, Story $story, ?Purchase $purchase = null): UserStoryUnlock
    {
        return DB::transaction(function () use ($user, $story, $purchase) {
            return UserStoryUnlock::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'story_id' => $story->id,
                ],
                [
                    'purchase_id' => $purchase?->id,
                    'unlocked_at' => now(),
                ]
            );
        });
    }

    public function completeStory(User $user, Story $story): array
    {
        return DB::transaction(function () use ($user, $story) {
            $progress = UserStoryProgress::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'story_id' => $story->id,
                ],
                [
                    'status' => 'completed',
                    'completed_at' => now(),
                ]
            );

            // Synchronize stories_done count on user player profile
            $completedCount = UserStoryProgress::where('user_id', $user->id)
                ->where('status', 'completed')
                ->count();

            if ($user->playerProfile) {
                $user->playerProfile->update([
                    'stories_done' => $completedCount,
                ]);
            }

            return [
                'success' => true,
                'story_id' => $story->id,
                'chapter_number' => $story->chapter_number,
                'status' => 'completed',
                'stories_done' => $completedCount,
            ];
        });
    }
}
