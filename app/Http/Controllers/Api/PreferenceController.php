<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PreferenceController extends Controller
{
    public const DEFAULTS = [
        'soundEffects' => true,
        'cameraMirror' => true,
        'dominantHand' => 'right',
        'highContrast' => false,
        'dailyReminder' => true,
    ];

    private function payload(User $user): array
    {
        return ['preferences' => array_replace(self::DEFAULTS, $user->preferences ?? []),
            'revision' => (int) $user->preferences_revision];
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->payload($request->user()->fresh())])->header('Cache-Control', 'no-store');
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['prohibited'],
            'revision' => ['required', 'integer', 'min:0'],
            'preferences' => ['required', 'array:soundEffects,cameraMirror,dominantHand,highContrast,dailyReminder', 'min:1'],
            'preferences.soundEffects' => ['sometimes', 'boolean'],
            'preferences.cameraMirror' => ['sometimes', 'boolean'],
            'preferences.dominantHand' => ['sometimes', Rule::in(['left', 'right'])],
            'preferences.highContrast' => ['sometimes', 'boolean'],
            'preferences.dailyReminder' => ['sometimes', 'boolean'],
        ]);

        return DB::transaction(function () use ($request, $data) {
            // Identity comes only from the authenticated session, never an IP or submitted ID.
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            if ((int) $data['revision'] !== (int) $user->preferences_revision) {
                return response()->json(['message' => 'Your preferences changed on another device. Review the latest settings and save again.',
                    'data' => $this->payload($user)], 409)->header('Cache-Control', 'no-store');
            }
            $changes = $data['preferences'];
            foreach ($changes as $key => $value) {
                if ($key !== 'dominantHand') $changes[$key] = (bool) $value;
            }
            $user->preferences = array_replace(self::DEFAULTS, $user->preferences ?? [], $changes);
            $user->preferences_revision++;
            $user->save();

            return response()->json(['data' => $this->payload($user)])->header('Cache-Control', 'no-store');
        });
    }
}
