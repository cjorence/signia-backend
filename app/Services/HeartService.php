<?php

namespace App\Services;

use App\Models\HeartTransaction;
use App\Models\PlayerProfile;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class HeartService
{
    private const REFRESH_MINUTES = 30;

    public function status(User $user): PlayerProfile
    {
        return $this->regenerate($user);
    }

    public function ensureCanAttempt(User $user): PlayerProfile
    {
        $profile = $this->regenerate($user);

        if ($profile->hearts <= 0) {
            throw ValidationException::withMessages([
                'hearts' => 'You have no hearts left. Please wait for regeneration or purchase more hearts.',
            ]);
        }

        return $profile;
    }

    public function deduct(User $user, int $amount, string $reason, array $metadata = []): PlayerProfile
    {
        return DB::transaction(function () use ($user, $amount, $reason, $metadata) {
            $this->regenerate($user);

            $profile = PlayerProfile::where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            $deductAmount = min($amount, $profile->hearts);

            if ($deductAmount <= 0) {
                return $profile;
            }

            $profile->update([
                'hearts' => $profile->hearts - $deductAmount,
                'next_heart_at' => $profile->next_heart_at ?? now()->addMinutes(self::REFRESH_MINUTES),
            ]);

            $this->log($user->id, null, 'lost', -$deductAmount, $reason, $metadata);

            return $profile->fresh();
        });
    }

    public function grant(User $user, int $amount, string $type, string $reason, ?Purchase $purchase = null, array $metadata = []): PlayerProfile
    {
        if ($type === 'purchase') {
            return $this->creditInventory(
                $user,
                $amount,
                $reason,
                $purchase,
                $metadata
            );
        }

        return DB::transaction(function () use ($user, $amount, $type, $reason, $purchase, $metadata) {
            $profile = $this->lockedProfile($user);

            $newHearts = min($profile->hearts + $amount, $profile->max_hearts);

            $profile->update([
                'hearts' => $newHearts,
                'next_heart_at' => $newHearts >= $profile->max_hearts ? null : $profile->next_heart_at,
            ]);

            $this->log($user->id, $purchase?->id, $type, $amount, $reason, $metadata);

            return $profile->fresh();
        });
    }

    /**
     * Credits purchased heart inventory. Inventory is separate from the
     * regenerating usable-heart meter, so purchased packs never overflow.
     */
    public function creditInventory(User $user, int $amount, string $reason, ?Purchase $purchase = null, array $metadata = []): PlayerProfile
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Heart inventory credit must be greater than zero.');
        }

        return DB::transaction(function () use ($user, $amount, $reason, $purchase, $metadata) {
            $profile = $this->lockedProfile($user);

            $profile->update([
                'heart_inventory' => $profile->heart_inventory + $amount,
            ]);

            $this->log($user->id, $purchase?->id, 'inventory_credit', $amount, $reason, $metadata);

            return $profile->fresh();
        });
    }

    /**
     * Moves inventory into usable hearts. A requested amount is capped by
     * available space, but cannot exceed the player's available inventory.
     */
    public function refillFromInventory(User $user, ?int $requestedAmount = null): PlayerProfile
    {
        if ($requestedAmount !== null && $requestedAmount <= 0) {
            throw new InvalidArgumentException('Requested heart refill must be greater than zero.');
        }

        $this->regenerate($user);

        return DB::transaction(function () use ($user, $requestedAmount) {
            $profile = $this->lockedProfile($user);
            $availableSpace = $profile->max_hearts - $profile->hearts;

            if ($availableSpace <= 0) {
                throw ValidationException::withMessages([
                    'hearts' => 'Your usable hearts are already full.',
                ]);
            }

            if ($profile->heart_inventory <= 0) {
                throw ValidationException::withMessages([
                    'heart_inventory' => 'You do not have any heart inventory available.',
                ]);
            }

            $refillAmount = min($requestedAmount ?? $availableSpace, $availableSpace);

            if ($profile->heart_inventory < $refillAmount) {
                throw ValidationException::withMessages([
                    'heart_inventory' => 'You do not have enough heart inventory for that refill.',
                ]);
            }

            $newHearts = $profile->hearts + $refillAmount;

            $profile->update([
                'hearts' => $newHearts,
                'heart_inventory' => $profile->heart_inventory - $refillAmount,
                'next_heart_at' => $newHearts >= $profile->max_hearts
                    ? null
                    : ($profile->next_heart_at ?? now()->addMinutes(self::REFRESH_MINUTES)),
            ]);

            $this->log(
                $user->id,
                null,
                'inventory_refill',
                -$refillAmount,
                'inventory_to_usable_hearts',
                ['hearts_added' => $refillAmount]
            );

            return $profile->fresh();
        });
    }

    public function regenerate(User $user): PlayerProfile
    {
        return DB::transaction(function () use ($user) {
            $profile = $this->getProfile($user);

            if (!$profile->next_heart_at || $profile->hearts >= $profile->max_hearts) {
                return $profile;
            }

            if (now()->lt($profile->next_heart_at)) {
                return $profile;
            }

            $currentHearts = $profile->hearts;
            $minutesPassed = $profile->next_heart_at->diffInMinutes(now());
            $heartsToAdd = 1 + intdiv($minutesPassed, self::REFRESH_MINUTES);
            $newHearts = min($currentHearts + $heartsToAdd, $profile->max_hearts);
            $heartsAdded = $newHearts - $currentHearts;

            $profile->update([
                'hearts' => $newHearts,
                'next_heart_at' => $newHearts >= $profile->max_hearts
                    ? null
                    : $profile->next_heart_at->copy()->addMinutes($heartsToAdd * self::REFRESH_MINUTES),
            ]);

            if ($heartsAdded > 0) {
                $this->log($user->id, null, 'regen', $heartsAdded, 'timer_replenish');
            }

            return $profile->fresh();
        });
    }

    public function transactions(User $user)
    {
        return HeartTransaction::where('user_id', $user->id)
            ->with('purchase')
            ->orderByDesc('created_at')
            ->get();
    }

    public function allTransactions()
    {
        return HeartTransaction::with(['user', 'purchase'])
            ->orderByDesc('created_at')
            ->get();
    }

    private function getProfile(User $user): PlayerProfile
    {
        return PlayerProfile::firstOrCreate(
            ['user_id' => $user->id],
            [
                'current_level' => 1,
                'total_xp' => 0,
                'streak' => 0,
                'hearts' => 5,
                'max_hearts' => 5,
                'heart_inventory' => 0,
            ]
        );
    }

    private function lockedProfile(User $user): PlayerProfile
    {
        $this->getProfile($user);

        return PlayerProfile::where('user_id', $user->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function log(int $userId, ?int $purchaseId, string $type, int $amount, ?string $reason = null, array $metadata = []): HeartTransaction
    {
        return HeartTransaction::create([
            'user_id' => $userId,
            'purchase_id' => $purchaseId,
            'type' => $type,
            'amount' => $amount,
            'reason' => $reason,
            'metadata' => $metadata,
        ]);
    }
}
