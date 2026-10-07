<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class CurriculumEventService
{
    private const CACHE_KEY = 'curriculum_sync_events';

    /**
     * Record a new curriculum synchronization event (archive or restore).
     */
    public static function record(string $type, int $id, string $name, ?string $reason = null): array
    {
        $events = Cache::get(self::CACHE_KEY, []);
        if (!is_array($events)) {
            $events = [];
        }

        $event = [
            'event_id'  => uniqid('cev_', true),
            'type'      => $type,
            'id'        => $id,
            'name'      => $name,
            'reason'    => $reason,
            'timestamp' => (int) round(microtime(true) * 1000),
        ];

        array_unshift($events, $event);
        // Retain the last 50 events
        $events = array_slice($events, 0, 50);

        Cache::put(self::CACHE_KEY, $events, now()->addDays(7));

        return $event;
    }

    /**
     * Retrieve recent curriculum synchronization events, optionally filtered by timestamp.
     */
    public static function getEvents(?int $since = null): array
    {
        $events = Cache::get(self::CACHE_KEY, []);
        if (!is_array($events)) {
            return [];
        }

        if ($since !== null && $since > 0) {
            return array_values(array_filter($events, fn ($e) => isset($e['timestamp']) && $e['timestamp'] > $since));
        }

        return $events;
    }
}
