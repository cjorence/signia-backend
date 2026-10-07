<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CurriculumEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurriculumEventController extends Controller
{
    /**
     * Get recent curriculum synchronization events.
     */
    public function index(Request $request): JsonResponse
    {
        $since = $request->has('since') ? $request->integer('since') : null;
        $events = CurriculumEventService::getEvents($since);

        return response()->json([
            'success' => true,
            'data'    => $events,
        ], 200);
    }
}
