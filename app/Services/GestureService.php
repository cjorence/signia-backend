<?php

namespace App\Services;

use App\Models\GestureLog;
use App\Models\Sign;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GestureService
{
    /**
     * Minimum confidence required to consider a gesture correct.
     */
    private const CORRECTNESS_THRESHOLD = 70.00;

    public function __construct(
        protected ProgressService $progressService,
        protected HeartService $heartService
    ) {}

    /**
     * Compare expected vs predicted, calculate correctness, store log,
     * deduct heart if incorrect, and trigger progress update.
     */
    public function storeGesture(int $userId, array $data): array
    {
        $user = User::findOrFail($userId);

        $this->heartService->ensureCanAttempt($user);

        $sign = Sign::findOrFail($data['sign_id']);
        if ((int) $sign->level_id !== (int) $data['level_id']) {
            throw ValidationException::withMessages([
                'sign_id' => 'The selected sign does not belong to the specified level.',
            ]);
        }

        // Expected labels are derived from the database. Prediction telemetry is
        // supplied by the browser until the AI service is moved server-side.
        $expected = strtolower(trim((string) ($sign->model_label ?: $sign->name)));
        $predicted = strtolower(trim($data['predicted_sign']));
        $confidence = (float) $data['confidence'];

        // Calculate correctness:
        // 1. Predicted label must match expected
        // 2. Confidence must meet threshold
        $isCorrect = ($expected === $predicted) && ($confidence >= self::CORRECTNESS_THRESHOLD);

        // Persist log + heart deduction + progress update atomically
        $result = DB::transaction(function () use ($user, $userId, $data, $sign, $expected, $isCorrect, $confidence) {

            if (! $isCorrect) {
                $this->heartService->deduct($user, 1, 'failed_gesture_attempt', [
                    'sign_id' => $data['sign_id'],
                    'level_id' => $data['level_id'],
                    'expected_sign' => $expected,
                    'predicted_sign' => $data['predicted_sign'],
                    'confidence' => $confidence,
                ]);
            }

            // 1. Store the gesture log
            $log = GestureLog::create([
                'user_id' => $userId,
                'sign_id' => $data['sign_id'],
                'level_id' => $data['level_id'],
                'expected_sign' => $expected,
                'predicted_sign' => $data['predicted_sign'],
                'confidence' => $confidence,
                'is_correct' => $isCorrect,
                'attempt_duration' => $data['attempt_duration'] ?? null,
            ]);

            // 2. Update progress (only if correct -> otherwise just track attempts)
            $this->progressService->recordAttempt(
                userId: $userId,
                signId: $data['sign_id'],
                levelId: $data['level_id'],
                confidence: $isCorrect ? $confidence : 0.0
            );

            $progress = $this->progressService->evaluateCompletion($userId, $sign->id);

            return [
                'log' => $log,
                'progress' => $progress,
            ];
        });

        $result['log']->load('sign');

        return [
            'gesture_log' => $result['log'],
            'progress' => $result['progress'],
            'is_correct' => $isCorrect,
        ];
    }

    /**
     * Get all gesture logs for a user.
     */
    public function getUserGestureLogs(int $userId, ?int $signId = null, ?int $levelId = null): Collection
    {
        $query = GestureLog::where('user_id', $userId)->with('sign');

        if ($signId) {
            $query->where('sign_id', $signId);
        }

        if ($levelId) {
            $query->where('level_id', $levelId);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get accuracy statistics for a user.
     */
    public function getUserAccuracy(int $userId): array
    {
        $total = GestureLog::where('user_id', $userId)->count();
        $correct = GestureLog::where('user_id', $userId)->where('is_correct', true)->count();

        $accuracy = $total > 0 ? round(($correct / $total) * 100, 2) : 0.0;

        return [
            'total_attempts' => $total,
            'correct_attempts' => $correct,
            'accuracy' => $accuracy,
        ];
    }
}
