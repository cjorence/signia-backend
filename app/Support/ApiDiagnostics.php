<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ApiDiagnostics
{
    public static function context(Request $request): array
    {
        return [
            'request_id' => $request->attributes->get('diagnostic_request_id'),
            'method' => $request->method(),
            // Use the route template, never a URL, query string or parameter value.
            'route' => $request->route()?->uri(),
            'user_id' => $request->user()?->getAuthIdentifier(),
        ];
    }

    public static function report(Throwable $error, Request $request): void
    {
        $frames = array_slice($error->getTrace(), 0, 12);
        Log::error('API exception', self::context($request) + [
            'exception_type' => get_class($error),
            'file' => self::sourcePath($error->getFile()),
            'line' => $error->getLine(),
            // Exception messages/arguments can contain SQL bindings, tokens or PII.
            'trace' => array_map(static fn (array $frame) => [
                'file' => isset($frame['file']) ? self::sourcePath($frame['file']) : null,
                'line' => $frame['line'] ?? null,
                'class' => $frame['class'] ?? null,
                'function' => $frame['function'] ?? null,
            ], $frames),
        ]);
    }

    private static function sourcePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $root = rtrim(str_replace('\\', '/', base_path()), '/').'/';

        return str_starts_with($path, $root) ? substr($path, strlen($root)) : basename($path);
    }
}
