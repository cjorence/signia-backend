<?php

namespace App\Services;

use App\Models\AdminNotification;
use App\Models\AdminNotificationSetting;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AdminNotificationService
{
    public const SETTINGS_KEY = 'admin_notification_settings';
    private const DEFAULT_SETTINGS = [
        'newUserAlerts' => true,
        'purchaseAlerts' => true,
        'trainingAlerts' => true,
    ];

    public function getSettings(): array
    {
        return array_replace(self::DEFAULT_SETTINGS, AdminNotificationSetting::find(1)?->preferences ?? []);
    }

    public function updateSettings(array $settings): array
    {
        return DB::transaction(function () use ($settings) {
            AdminNotificationSetting::firstOrCreate(['id' => 1], ['preferences' => self::DEFAULT_SETTINGS]);
            $record = AdminNotificationSetting::whereKey(1)->lockForUpdate()->firstOrFail();
            $updated = array_replace(self::DEFAULT_SETTINGS, $record->preferences);
            foreach (self::DEFAULT_SETTINGS as $key => $default) {
                if (array_key_exists($key, $settings)) {
                    $updated[$key] = (bool) $settings[$key];
                }
            }
            $record->update(['preferences' => $updated]);

            return $updated;
        });
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
        $currency = strtoupper((string) ($purchase->currency ?: 'PHP'));
        $packageKey = $purchase->package_key ?? $purchase->product_name ?? 'Heart Refill Package';

        return AdminNotification::create([
            'type' => 'heart_purchase',
            'title' => 'Hearts Store Purchase',
            'message' => "{$userName} purchased {$packageKey} for {$currency} {$amount}.",
            'data' => [
                'purchase_id' => $purchase->id,
                'user_id' => $user?->id,
                'user_name' => $userName,
                'amount' => $purchase->amount,
                'currency' => $currency,
                'package_key' => $packageKey,
            ],
            'severity' => 'success',
        ]);
    }

    public function notifyTrainingComplete(string $jobId, string $category, float $accuracy, ?string $modelPath = null): ?AdminNotification
    {
        $settings = $this->getSettings();
        if (!($settings['trainingAlerts'] ?? true)) {
            return null;
        }

        $accuracyPercent = round($accuracy * 100, 1);

        return DB::transaction(function () use ($jobId, $category, $accuracy, $accuracyPercent, $modelPath) {
            $claimed = DB::table('admin_notification_events')->insertOrIgnore([
                'event_key' => 'training:'.strtolower($jobId),
                'created_at' => now(),
            ]);
            if (! $claimed) {
                return null;
            }

            return AdminNotification::create([
                'type' => 'model_training',
                'title' => 'AI Model Training Completed',
                'message' => "Model checkpoint for category '{$category}' finished training with {$accuracyPercent}% accuracy.",
                'data' => [
                    'job_id' => $jobId,
                    'category' => $category,
                    'accuracy' => $accuracy,
                    'model_path' => $modelPath,
                ],
                'severity' => 'success',
            ]);
        });
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
