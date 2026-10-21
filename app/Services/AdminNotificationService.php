<?php

namespace App\Services;

use App\Models\AdminNotification;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class AdminNotificationService
{
    public const SETTINGS_KEY = 'admin_notification_settings';

    public function getSettings(): array
    {
        return Cache::get(self::SETTINGS_KEY, [
            'newUserAlerts' => true,
            'purchaseAlerts' => true,
            'trainingAlerts' => true,
        ]);
    }

    public function updateSettings(array $settings): array
    {
        $current = $this->getSettings();
        $updated = [
            'newUserAlerts' => array_key_exists('newUserAlerts', $settings) ? (bool) $settings['newUserAlerts'] : $current['newUserAlerts'],
            'purchaseAlerts' => array_key_exists('purchaseAlerts', $settings) ? (bool) $settings['purchaseAlerts'] : $current['purchaseAlerts'],
            'trainingAlerts' => array_key_exists('trainingAlerts', $settings) ? (bool) $settings['trainingAlerts'] : $current['trainingAlerts'],
        ];

        Cache::forever(self::SETTINGS_KEY, $updated);

        return $updated;
    }

    public function notifyNewUser(User $user): ?AdminNotification
    {
        $settings = $this->getSettings();
        if (!($settings['newUserAlerts'] ?? true)) {
            return null;
        }

        return AdminNotification::create([
            'type' => 'user_registered',
            'title' => 'New Learner Registration',
            'message' => "{$user->name} ({$user->email}) registered an account on Signia.",
            'data' => [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'severity' => 'info',
        ]);
    }

    public function notifyHeartPurchase(Purchase $purchase, ?User $user = null): ?AdminNotification
    {
        $settings = $this->getSettings();
        if (!($settings['purchaseAlerts'] ?? true)) {
            return null;
        }

        $user = $user ?? $purchase->user;
        $userName = $user ? $user->name : 'A learner';
        $amount = number_format((float) ($purchase->amount ?? 0), 2);
        $packageKey = $purchase->package_key ?? $purchase->product_name ?? 'Heart Refill Package';

        return AdminNotification::create([
            'type' => 'heart_purchase',
            'title' => 'Hearts Store Purchase',
            'message' => "{$userName} purchased {$packageKey} for \${$amount}.",
            'data' => [
                'purchase_id' => $purchase->id,
                'user_id' => $user?->id,
                'user_name' => $userName,
                'amount' => $purchase->amount,
                'package_key' => $packageKey,
            ],
            'severity' => 'success',
        ]);
    }

    public function notifyTrainingComplete(string $category, float $accuracy, ?string $modelPath = null): ?AdminNotification
    {
        $settings = $this->getSettings();
        if (!($settings['trainingAlerts'] ?? true)) {
            return null;
        }

        $accuracyPercent = round($accuracy * 100, 1);

        return AdminNotification::create([
            'type' => 'model_training',
            'title' => 'AI Model Training Completed',
            'message' => "Model checkpoint for category '{$category}' finished training with {$accuracyPercent}% accuracy.",
            'data' => [
                'category' => $category,
                'accuracy' => $accuracy,
                'model_path' => $modelPath,
            ],
            'severity' => 'success',
        ]);
    }

    public function getNotifications(int $limit = 50): array
    {
        $notifications = AdminNotification::recent()->take($limit)->get();
        $unreadCount = AdminNotification::unread()->count();

        return [
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ];
    }

    public function markAsRead(int $id): bool
    {
        $notification = AdminNotification::find($id);
        if ($notification && is_null($notification->read_at)) {
            $notification->update(['read_at' => now()]);
            return true;
        }

        return false;
    }

    public function markAllAsRead(): int
    {
        return AdminNotification::unread()->update(['read_at' => now()]);
    }

    public function destroy(int $id): bool
    {
        $notification = AdminNotification::find($id);
        if ($notification) {
            return (bool) $notification->delete();
        }

        return false;
    }

    public function clearAll(): int
    {
        return AdminNotification::query()->delete();
    }
}
