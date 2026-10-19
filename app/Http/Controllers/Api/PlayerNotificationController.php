<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HeartTransaction;
use App\Models\RefundRequest;
use App\Models\UserNotificationState;
use App\Services\CurriculumEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlayerNotificationController extends Controller
{
    /**
     * Get synchronized notifications for the authenticated player.
     * Combines general curriculum updates and personal account alerts.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $state = UserNotificationState::firstOrCreate(
            ['user_id' => $user->id],
            [
                'read_ids' => [],
                'dismissed_ids' => [],
                'all_read_at' => 0,
                'cleared_at' => 0,
            ]
        );

        $readIds = is_array($state->read_ids) ? $state->read_ids : [];
        $dismissedIds = is_array($state->dismissed_ids) ? $state->dismissed_ids : [];
        $allReadAt = (int) ($state->all_read_at ?? 0);
        $clearedAt = (int) ($state->cleared_at ?? 0);

        $notifications = [];

        // 1. Specific / Personal Notifications: Refunds
        $refunds = RefundRequest::where('user_id', $user->id)
            ->whereIn('status', ['approved', 'rejected', 'refunded'])
            ->latest('updated_at')
            ->take(30)
            ->get();

        foreach ($refunds as $refund) {
            $status = $refund->status;
            $providerStatus = $refund->provider_status ?? '';
            $id = "refund-{$refund->id}-{$status}-{$providerStatus}";

            $title = match ($status) {
                'approved' => in_array($providerStatus, ['unknown', 'failed'], true) ? 'Refund needs attention' : 'Refund processing',
                'refunded' => 'Refunded',
                'rejected' => 'Refund declined',
                default => 'Refund update',
            };

            $message = "Order #{$refund->purchase_id}: " . match ($status) {
                'rejected' => 'Your refund request was declined.',
                'refunded' => 'Your refund has been sent to your original payment method.',
                default => in_array($providerStatus, ['unknown', 'failed'], true)
                    ? 'Your refund has not been confirmed. Our team needs to check the payment.'
                    : 'Your request was approved and the refund is being processed.',
            };

            $notes = $refund->admin_notes ?: $refund->decision_reason;
            if ($notes) {
                $notes = preg_replace('/payment gateway processing fee/i', 'processing fee', $notes);
                $notes = preg_replace('/payment gateway/i', 'processing', $notes);
                $notes = preg_replace('/on our ledger/i', 'in our records', $notes);
            }

            $timestamp = $refund->updated_at
                ? (int) ($refund->updated_at->timestamp * 1000)
                : (int) round(microtime(true) * 1000);

            $notifications[] = [
                'id' => $id,
                'category' => 'personal',
                'title' => $title,
                'message' => $message,
                'reason' => $notes ?: null,
                'type' => $status === 'refunded' ? 'success' : ($status === 'rejected' ? 'warning' : 'info'),
                'timestamp' => $timestamp,
                'link' => '/player/profile?tab=purchases',
            ];
        }

        // 2. Specific / Personal Notifications: Heart Grants
        $heartGrants = HeartTransaction::where('user_id', $user->id)
            ->where('type', 'admin_grant')
            ->latest('created_at')
            ->take(30)
            ->get();

        foreach ($heartGrants as $grant) {
            $timestamp = $grant->created_at
                ? (int) ($grant->created_at->timestamp * 1000)
                : (int) round(microtime(true) * 1000);

            $notifications[] = [
                'id' => "heart-grant-{$grant->id}",
                'category' => 'personal',
                'title' => 'Hearts added',
                'message' => "{$grant->amount} hearts were added to your account.",
                'reason' => $grant->reason ?: null,
                'type' => 'success',
                'timestamp' => $timestamp,
                'link' => '/player/store',
            ];
        }

        // 3. General Curriculum & Platform Notifications
        $curriculumEvents = CurriculumEventService::getEvents();
        foreach ($curriculumEvents as $event) {
            $eventTimestamp = (int) ($event['timestamp'] ?? round(microtime(true) * 1000));
            $type = $event['type'] ?? 'info';
            $id = $event['event_id'] ?? "curriculum-{$type}-{$event['id']}-{$eventTimestamp}";

            $uiType = str_contains($type, 'archived') ? 'warning' : 'success';
            if ($type === 'platform-maintenance') {
                $uiType = ($event['id'] ?? 0) === 1 ? 'warning' : 'success';
            }

            $name = $event['name'] ?? 'Curriculum';
            $title = match ($type) {
                'lesson-archived' => 'Lesson Archived',
                'lesson-restored' => 'Lesson Restored',
                'lesson-added' => 'New Lesson Added',
                'category-archived' => 'Category Archived',
                'category-restored' => 'Category Restored',
                'category-added' => 'New Category Added',
                'story-archived' => 'Story Chapter Archived',
                'story-restored' => 'Story Chapter Restored',
                'story-added' => 'New Story Chapter Available',
                'platform-maintenance' => (($event['id'] ?? 0) === 1 ? 'Platform Maintenance Notice' : 'System Operational'),
                default => 'Curriculum Notice',
            };

            $message = match ($type) {
                'lesson-archived' => "The lesson \"{$name}\" was archived by an administrator.",
                'lesson-restored' => "The lesson \"{$name}\" has been restored and is ready to practice!",
                'lesson-added' => "A new lesson \"{$name}\" is now available to learn and practice!",
                'category-archived' => "The category \"{$name}\" was archived by an administrator.",
                'category-restored' => "The category \"{$name}\" has been restored to the roadmap!",
                'category-added' => "A new category \"{$name}\" has been added to the curriculum!",
                'story-archived' => "The story chapter \"{$name}\" was archived by an administrator.",
                'story-restored' => "The story chapter \"{$name}\" has been restored to adventures!",
                'story-added' => "A new story chapter \"{$name}\" has been added! Embark on a new FSL adventure.",
                'platform-maintenance' => ($event['name'] ?: ((($event['id'] ?? 0) === 1) ? 'Signia is currently undergoing routine curriculum updates. Practice will resume shortly.' : 'Platform maintenance has concluded. All learning systems are fully operational!')),
                default => "Curriculum update: {$name}",
            };

            $link = str_contains($type, 'story') ? '/player/story' : (str_contains($type, 'lesson') || str_contains($type, 'category') ? '/player/lessons' : null);

            $notifications[] = [
                'id' => $id,
                'category' => 'curriculum',
                'title' => $title,
                'message' => $message,
                'reason' => $event['reason'] ?? null,
                'type' => $uiType,
                'timestamp' => $eventTimestamp,
                'link' => $link,
            ];
        }

        // Filter dismissed items and items cleared before cleared_at
        $filtered = array_values(array_filter($notifications, function ($item) use ($dismissedIds, $clearedAt) {
            if (in_array($item['id'], $dismissedIds, true)) {
                return false;
            }
            if ($clearedAt > 0 && $item['timestamp'] <= $clearedAt) {
                return false;
            }
            return true;
        }));

        // Sort descending by timestamp
        usort($filtered, fn ($a, $b) => $b['timestamp'] <=> $a['timestamp']);

        // Limit to 50 most recent items
        $filtered = array_slice($filtered, 0, 50);

        // Apply read status
        $readSet = array_flip($readIds);
        foreach ($filtered as &$item) {
            $item['read'] = isset($readSet[$item['id']]) || ($allReadAt > 0 && $item['timestamp'] <= $allReadAt);
        }
        unset($item);

        $unreadCount = count(array_filter($filtered, fn ($item) => !$item['read']));

        return response()->json([
            'success' => true,
            'data' => $filtered,
            'unread_count' => $unreadCount,
            'all_read_at' => $allReadAt,
            'cleared_at' => $clearedAt,
        ], 200)->header('Cache-Control', 'no-store');
    }

    /**
     * Mark an individual notification as read.
     */
    public function markAsRead(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $state = UserNotificationState::firstOrCreate(['user_id' => $user->id]);

        $readIds = is_array($state->read_ids) ? $state->read_ids : [];
        if (!in_array($validated['id'], $readIds, true)) {
            $readIds[] = $validated['id'];
            // Retain up to 100 most recent read IDs
            $state->read_ids = array_slice($readIds, -100);
            $state->save();
        }

        return response()->json(['success' => true]);
    }

    /**
     * Mark all current notifications as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $state = UserNotificationState::firstOrCreate(['user_id' => $user->id]);

        $state->all_read_at = (int) round(microtime(true) * 1000);
        $state->read_ids = [];
        $state->save();

        return response()->json([
            'success' => true,
            'all_read_at' => $state->all_read_at,
        ]);
    }

    /**
     * Dismiss / delete an individual notification.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $state = UserNotificationState::firstOrCreate(['user_id' => $user->id]);

        $dismissedIds = is_array($state->dismissed_ids) ? $state->dismissed_ids : [];
        if (!in_array($id, $dismissedIds, true)) {
            $dismissedIds[] = $id;
            // Retain up to 100 most recent dismissed IDs
            $state->dismissed_ids = array_slice($dismissedIds, -100);
            $state->save();
        }

        return response()->json(['success' => true]);
    }

    /**
     * Clear all notifications for the user.
     */
    public function clearAll(Request $request): JsonResponse
    {
        $user = $request->user();
        $state = UserNotificationState::firstOrCreate(['user_id' => $user->id]);

        $now = (int) round(microtime(true) * 1000);
        $state->cleared_at = $now;
        $state->all_read_at = $now;
        $state->read_ids = [];
        $state->dismissed_ids = [];
        $state->save();

        return response()->json([
            'success' => true,
            'cleared_at' => $state->cleared_at,
        ]);
    }
}

