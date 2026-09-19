<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FinisherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinisherController extends Controller
{
    public function __construct(
        protected FinisherService $finisherService
    ) {}

    /**
     * Get finishers board entries.
     */
    public function index(Request $request): JsonResponse
    {
        $levelId = $request->filled('level_id') ? (int) $request->input('level_id') : null;
        $limit = min(100, max(1, (int) $request->input('limit', 50)));

        $finishers = $this->finisherService->getFinishers($levelId, $limit);

        return response()->json([
            'success' => true,
            'data'    => $finishers,
        ], 200);
    }
}
