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
    public const REFRESH_MINUTES = 30;

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
            $usableAdded = $newHearts - $profile->hearts;
            $inventoryAdded = $type === 'admin_grant' ? max(0, $amount - $usableAdded) : 0;

            $profile->update([
                'hearts' => $newHearts,
                'heart_inventory' => $profile->heart_inventory + $inventoryAdded,
                'next_heart_at' => $newHearts >= $profile->max_hearts ? null : $profile->next_heart_at,
            ]);

            $this->log($user->id, $purchase?->id, $type, $amount, $reason, array_merge($metadata, [
                'usable_hearts_added' => $usableAdded,
                'inventory_hearts_added' => $inventoryAdded,
            ]));

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

            if ($requestedAmount !== null) {
                if ($profile->heart_inventory < $requestedAmount) {
                    throw ValidationException::withMessages([
                        'heart_inventory' => 'You do not have enough heart inventory for that refill.',
                    ]);
                }

                $refillAmount = min($requestedAmount, $availableSpace);
            } else {
                $refillAmount = min($availableSpace, $profile->heart_inventory);
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
            ->limit(100)
            ->get();
    }

    public function allTransactions()
    {
        return HeartTransaction::with(['user', 'purchase'])
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Calculates consumable heart usage for a specific purchase using
     * Free-Hearts-First priority and chronological FIFO ledger accounting for paid packs.
     *
     * @return array{hearts_purchased: int, hearts_remaining: int, hearts_used: int, hearts_intact: bool}
     */
    public function getPurchaseConsumableUsage(Purchase $purchase): array
    {
        $heartsPurchased = (int) ($purchase->quantity ?? 0);
        if (! in_array($purchase->product_type, ['heart', 'hearts'], true) || $heartsPurchased <= 0) {
            return [
                'hearts_purchased' => 0,
                'hearts_remaining' => 0,
                'hearts_used' => 0,
                'hearts_intact' => true,
            ];
        }

        $transactions = HeartTransaction::where('user_id', $purchase->user_id)
            ->whereIn('type', ['inventory_credit', 'admin_grant', 'inventory_refill', 'refund'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $freeAvailable = 0;
        $paidPackages = [];

        foreach ($transactions as $tx) {
            if ($tx->type === 'admin_grant') {
                $invAdded = (int) (data_get($tx->metadata, 'inventory_hearts_added') ?? 0);
                $freeAvailable += $invAdded;
            } elseif ($tx->type === 'inventory_credit' && $tx->purchase_id) {
                $paidPackages[$tx->purchase_id] = [
                    'id' => $tx->purchase_id,
                    'purchased' => (int) $tx->amount,
                    'remaining' => (int) $tx->amount,
                ];
            } elseif ($tx->type === 'refund' && $tx->purchase_id) {
                $refundAmt = abs((int) $tx->amount);
                if (isset($paidPackages[$tx->purchase_id])) {
                    $avail = $paidPackages[$tx->purchase_id]['remaining'];
                    $fromPkg = min($refundAmt, $avail);
                    $paidPackages[$tx->purchase_id]['remaining'] -= $fromPkg;
                    $refundAmt -= $fromPkg;
                }
                if ($refundAmt > 0) {
                    foreach ($paidPackages as &$pkg) {
                        if ($pkg['remaining'] > 0) {
                            $take = min($refundAmt, $pkg['remaining']);
                            $pkg['remaining'] -= $take;
                            $refundAmt -= $take;
                            if ($refundAmt <= 0) {
                                break;
                            }
                        }
                    }
                    unset($pkg);
                }
            } elseif ($tx->type === 'inventory_refill') {
                $needed = abs((int) $tx->amount);
                // Free/admin-granted hearts burn first (Option 1)
                $fromFree = min($needed, $freeAvailable);
                $freeAvailable -= $fromFree;
                $needed -= $fromFree;

                if ($needed > 0) {
                    foreach ($paidPackages as &$pkg) {
                        if ($pkg['remaining'] > 0) {
                            $take = min($needed, $pkg['remaining']);
                            $pkg['remaining'] -= $take;
                            $needed -= $take;
                            if ($needed <= 0) {
                                break;
                            }
                        }
                    }
                    unset($pkg);
                }
            }
        }

        $packageData = $paidPackages[$purchase->id] ?? [
            'purchased' => $heartsPurchased,
            'remaining' => 0,
        ];

        $remaining = max(0, $packageData['remaining']);
        $used = max(0, $heartsPurchased - $remaining);

        return [
            'hearts_purchased' => $heartsPurchased,
            'hearts_remaining' => $remaining,
            'hearts_used' => $used,
            'hearts_intact' => $used === 0 && $remaining >= $heartsPurchased,
        ];
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
