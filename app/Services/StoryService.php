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

    public function createLaunchTicket(User $user, Story $story): array
    {
        if (! $story->isUnlockedFor($user)) {
            throw ValidationException::withMessages([
                'story' => 'You must unlock Chapter '.$story->chapter_number.' to play.',
            ]);
        }

        $appKey = (string) config('app.key');
        if (str_starts_with($appKey, 'base64:')) {
            $appKey = base64_decode(substr($appKey, 7));
        }

        $payload = [
            'user_id' => $user->id,
            'story_id' => $story->id,
            'chapter_number' => (int) $story->chapter_number,
            'expires_at' => now()->addMinutes(5)->timestamp,
            'nonce' => \Illuminate\Support\Str::random(16),
        ];

        $encodedPayload = rtrim(strtr(base64_encode(json_encode($payload, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', $encodedPayload, $appKey);
        $ticket = $encodedPayload.'.'.$signature;

        return [
            'ticket' => $ticket,
            'chapter_number' => (int) $story->chapter_number,
            'story_id' => $story->id,
            'expires_at' => $payload['expires_at'],
        ];
    }

    public function verifyLaunchTicket(string $ticket, int|string $chapterNumber): array
    {
        $parts = explode('.', $ticket, 2);
        if (count($parts) !== 2) {
            throw ValidationException::withMessages([
                'ticket' => 'Invalid ticket format.',
            ]);
        }

        [$encodedPayload, $signature] = $parts;

        $appKey = (string) config('app.key');
        if (str_starts_with($appKey, 'base64:')) {
            $appKey = base64_decode(substr($appKey, 7));
        }

        $expectedSignature = hash_hmac('sha256', $encodedPayload, $appKey);
        if (! hash_equals($expectedSignature, $signature)) {
            throw ValidationException::withMessages([
                'ticket' => 'Invalid ticket signature.',
            ]);
        }

        $json = base64_decode(strtr($encodedPayload, '-_', '+/'));
        $payload = json_decode($json, true);

        if (! is_array($payload) || ! isset($payload['expires_at'], $payload['chapter_number'])) {
            throw ValidationException::withMessages([
                'ticket' => 'Malformed ticket payload.',
            ]);
        }

        if (now()->timestamp > (int) $payload['expires_at']) {
            throw ValidationException::withMessages([
                'ticket' => 'Launch ticket has expired. Please launch from Signia.',
            ]);
        }

        if ((int) $payload['chapter_number'] !== (int) $chapterNumber) {
            throw ValidationException::withMessages([
                'ticket' => 'Ticket chapter does not match requested chapter.',
            ]);
        }

        return [
            'valid' => true,
            'user_id' => $payload['user_id'] ?? null,
            'story_id' => $payload['story_id'] ?? null,
            'chapter_number' => (int) $payload['chapter_number'],
        ];
    }

    public function getPackageFilePath(?Story $story = null): string
    {
        if ($story && ! empty($story->file_name)) {
            $customPath = storage_path('app/stories/'.$story->file_name);
            if (file_exists($customPath)) {
                return $customPath;
            }
        }

        $basePath = storage_path('app/stories/base.pck');
        if (! file_exists($basePath)) {
            $altPath = storage_path('app/stories/index.pck');
            if (file_exists($altPath)) {
                return $altPath;
            }
        }

        return $basePath;
    }

    public function resolveAuthorizedPackage(string $ticket, int|string $chapterNumber): array
    {
        $verification = $this->verifyLaunchTicket($ticket, $chapterNumber);

        $story = Story::where('id', $verification['story_id'])
            ->orWhere('chapter_number', (int) $chapterNumber)
            ->first();

        if (! $story) {
            throw ValidationException::withMessages([
                'chapter' => 'Story chapter not found.',
            ]);
        }

        $filePath = $this->getPackageFilePath($story);
        if (! file_exists($filePath)) {
            throw new \RuntimeException('Game chapter package file not found on server.');
        }

        return [
            'file_path' => $filePath,
            'file_name' => $story->file_name ?: 'chapter_'.$story->chapter_number.'.pck',
            'size' => filesize($filePath),
            'story' => $story,
        ];
    }
}
