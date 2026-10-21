<?php

namespace App\Http\Middleware;

use App\Support\ApiDiagnostics;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class TrackApiFailures
{
    public function handle(Request $request, Closure $next): Response
    {
        // Generate locally; never trust a caller-supplied diagnostic identifier.
        $id = (string) Str::uuid();
        $request->attributes->set('diagnostic_request_id', $id);
        $started = hrtime(true);
        Log::withContext(['request_id' => $id]);

        try {
            $response = $next($request);
            $response->headers->set('X-Request-ID', $id);

            if ($response->getStatusCode() >= 500) {
                Log::error('API request failed', ApiDiagnostics::context($request) + [
                    'status' => $response->getStatusCode(),
                    'duration_ms' => round((hrtime(true) - $started) / 1_000_000, 1),
                ]);
                if ($response instanceof JsonResponse) {
                    $payload = $response->getData(true);
                    if (is_array($payload) && ! array_is_list($payload)) {
                        $payload['request_id'] = $id;
                        $response->setData($payload);
                    }
                }
            }

            return $response;
        } finally {
            Log::withoutContext(['request_id']);
        }
    }
}
