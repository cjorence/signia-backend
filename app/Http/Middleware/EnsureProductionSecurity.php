<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureProductionSecurity
{
    public function handle(Request $request, Closure $next)
    {
        if (app()->environment('production')) {
            $secret = (string) config('services.turnstile.secret_key');
            if (config('app.debug') || $secret === '' || preg_match('/^[123]x0+AA$/', $secret)) {
                return response()->json(['message' => 'Account verification is temporarily unavailable.'], 503);
            }
        }
        return $next($request);
    }
}
