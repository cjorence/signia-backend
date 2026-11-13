<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;

class RecognitionController extends Controller
{
    public function predict(Request $request)
    {
        $data = $request->validate([
            'mode' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9_-]+$/'],
            'model_label' => ['sometimes', 'string', 'max:80'],
            'image' => ['sometimes', 'nullable', 'string', 'max:2000000'],
            'landmarks' => ['sometimes', 'array'],
            'landmarks_126' => ['sometimes', 'array', 'size:126'],
            'frames' => ['sometimes', 'array', 'min:1', 'max:30'],
            'hand_detected' => ['sometimes', 'boolean'],
            'stream_id' => ['required', 'string', 'max:100'],
            'stabilize' => ['sometimes', 'boolean'],
        ]);
        $hasImage = isset($data['image']) && is_string($data['image']) && $data['image'] !== '';
        $hasLandmarks = isset($data['landmarks']) || isset($data['landmarks_126']) || isset($data['frames']) || array_key_exists('hand_detected', $data);
        if (! $hasImage && ! $hasLandmarks) {
            return response()->json(['message' => 'A camera frame or hand landmarks are required.'], 422);
        }
        if ($hasImage && ! str_starts_with($data['image'], 'data:image/jpeg;base64,') && ! str_starts_with($data['image'], 'data:image/png;base64,')) {
            return response()->json(['message' => 'A JPEG or PNG camera frame is required.'], 422);
        }
        if (isset($data['landmarks']) && (! array_is_list($data['landmarks']) || ! in_array(count($data['landmarks']), [63, 126], true) || ! $this->validLandmarkNumbers($data['landmarks']))) {
            return response()->json(['message' => 'Invalid hand landmarks.'], 422);
        }
        if (isset($data['landmarks_126']) && (! array_is_list($data['landmarks_126']) || ! $this->validLandmarkNumbers($data['landmarks_126']))) {
            return response()->json(['message' => 'Invalid hand landmarks.'], 422);
        }
        if (isset($data['frames'])) {
            if (! array_is_list($data['frames'])) {
                return response()->json(['message' => 'Invalid sign sample.'], 422);
            }
            foreach ($data['frames'] as $frame) {
                if (! is_array($frame) || ! array_is_list($frame) || count($frame) !== 126 || ! $this->validLandmarkNumbers($frame)) {
                    return response()->json(['message' => 'Invalid hand landmarks.'], 422);
                }
            }
        }
        // A caller cannot choose another account's namespace or access the server camera.
        $data['stream_id'] = hash_hmac('sha256', $request->user()->id.':'.$data['stream_id'], config('app.key'));
        $data['frame_source'] = 'client';
        return $this->forward('predict', 'POST', $data);
    }

    private function validLandmarkNumbers(array $values): bool
    {
        foreach ($values as $value) {
            if ((! is_int($value) && ! is_float($value)) || ! is_finite($value) || abs($value) > 4) {
                return false;
            }
        }
        return true;
    }

    public function greetingClip(Request $request)
    {
        return $this->forwardClip($request, 'predict/greeting-clip');
    }

    public function signClip(Request $request)
    {
        $data = $request->validate([
            'mode' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9_-]+$/'],
        ]);
        return $this->forwardClip($request, 'predict/admin-sign-clip', ['mode' => strtolower($data['mode'])]);
    }

    private function forwardClip(Request $request, string $path, array $data = [])
    {
        if (strlen($request->getContent()) > 131072) {
            return response()->json(['message' => 'Sign recording too large.'], 413);
        }
        $frames = $request->json('frames');
        if (! is_array($frames) || ! array_is_list($frames) || count($frames) !== 30) {
            return response()->json(['message' => 'Expected 30 sign samples.'], 422);
        }
        foreach ($frames as $frame) {
            if (! is_array($frame) || ! array_is_list($frame) || count($frame) !== 126) {
                return response()->json(['message' => 'Invalid sign sample.'], 422);
            }
            foreach ($frame as $value) {
                if ((! is_int($value) && ! is_float($value)) || ! is_finite($value) || abs($value) > 4) {
                    return response()->json(['message' => 'Invalid hand landmarks.'], 422);
                }
            }
        }
        return $this->forward($path, 'POST', $data + ['frames' => $frames]);
    }

    public function admin(Request $request, string $path)
    {
        $get = in_array($path, ['api/train/capabilities', 'api/train/labels', 'api/train/stats', 'models', 'modes'], true)
            || preg_match('#^api/train/status/[a-f0-9-]{36}$#D', $path);
        $download = preg_match('#^model_file/([A-Za-z0-9_-]+/)*[A-Za-z0-9_.-]+\.(pt|pth|keras|pkl|json|npy)$#D', $path);
        if ($request->isMethod('get') && ($get || $download)) {
            return $this->forward($path, 'GET', [], null, (bool) $download);
        }
        if ($request->isMethod('post') && in_array($path, ['api/train/start', 'api/train/upload_video'], true)
            && config('recognition.training_enabled') !== true) {
            return response()->json([
                'status' => 'error', 'code' => 'training_disabled',
                'message' => 'Hosted training is disabled. Use the local admin setup to collect data and train. Recognition remains available.',
            ], 409)->header('Cache-Control', 'no-store');
        }
        if ($request->isMethod('post') && $path === 'api/train/start') {
            $data = $request->validate(['category' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9_ -]+$/']]);
            $response = $this->forward($path, 'POST', $data);
            if ($response->isSuccessful() && ($response->getData(true)['status'] ?? '') !== 'error' && ($response->getData(true)['success'] ?? true) !== false) {
                app(\App\Services\AdminService::class)->logAction($request->user(), 'Started model training for '.$data['category']);
            }
            return $response;
        }
        if ($request->isMethod('post') && $path === 'api/train/upload_video') {
            $data = $request->validate([
                'category' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9_ -]+$/'],
                'label' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9_ -]+$/'],
                'video' => ['required', 'file', 'mimes:mp4,mov,webm,mkv,avi', 'max:20480'],
            ]);
            unset($data['video']);
            $response = $this->forward($path, 'POST', $data, $request->file('video'));
            if ($response->isSuccessful() && ($response->getData(true)['status'] ?? '') !== 'error' && ($response->getData(true)['success'] ?? true) !== false) {
                app(\App\Services\AdminService::class)->logAction($request->user(), 'Uploaded training video for '.$data['category']);
            }
            return $response;
        }
        abort(404);
    }

    private function forward(string $path, string $method, array $data = [], $file = null, bool $download = false)
    {
        $base = rtrim((string) config('recognition.url'), '/');
        $token = (string) config('recognition.token');
        if (! in_array(parse_url($base, PHP_URL_SCHEME), ['http', 'https'], true)
            || (app()->environment('production') && strlen($token) < 32)) {
            return response()->json(['message' => 'Recognition service is not configured.'], 503);
        }
        $client = Http::acceptJson()->connectTimeout(3)->timeout($file || $download ? 120 : 15)
            ->withOptions(['allow_redirects' => false, 'stream' => $download]);
        if ($requestId = request()->attributes->get('diagnostic_request_id')) {
            $client = $client->withHeaders(['X-Request-ID' => $requestId]);
        }
        if ($token !== '') {
            $client = $client->withToken($token);
        }
        $stream = null;
        try {
            if ($file) {
                $stream = fopen($file->getRealPath(), 'rb');
                $extension = strtolower($file->getClientOriginalExtension());
                $client = $client->attach('video', $stream, 'upload.'.$extension, ['Content-Type' => $file->getMimeType()]);
            }
            $upstream = $method === 'GET' ? $client->get($base.'/'.$path) : $client->post($base.'/'.$path, $data);
        } catch (ConnectionException $e) {
            Log::warning('Recognition connection failed', ['exception_type' => get_class($e)]);
            return response()->json(['message' => 'Recognition is temporarily unavailable. Please retry.'], 503);
        } finally {
            if (is_resource($stream)) fclose($stream);
        }
        if ($upstream->serverError() || $upstream->redirect() || in_array($upstream->status(), [401, 403], true)) {
            Log::warning('Recognition upstream unavailable', ['upstream_status' => $upstream->status()]);
            return response()->json(['message' => 'Recognition is temporarily unavailable. Please retry.'], 503);
        }
        if ($download && $upstream->successful()) {
            $body = $upstream->toPsrResponse()->getBody();
            return response()->streamDownload(function () use ($body) {
                try {
                    while (! $body->eof()) echo $body->read(65536);
                } finally {
                    $body->close();
                }
            }, basename($path), ['Content-Type' => 'application/octet-stream', 'Cache-Control' => 'private, no-store']);
        }
        $payload = $upstream->json();
        if (! is_array($payload)) {
            return response()->json(['message' => 'Invalid recognition service response.'], 502);
        }

        if ($path === 'models' && $upstream->successful()) {
            $payload['admin_greeting_clip'] = ($payload['greeting_clip'] ?? false) === true;
            $payload['admin_sign_clip_modes'] = $payload['admin_sign_clip_modes'] ?? [];
        }
        if ($path === 'api/train/capabilities' && $upstream->successful()) {
            $payload['training_enabled'] = config('recognition.training_enabled') === true
                && ($payload['training_enabled'] ?? false) === true;
        }

        if ($upstream->successful() && preg_match('#^api/train/status/#', $path) && ($payload['job']['status'] ?? '') === 'completed') {
            try {
                $category = $payload['job']['category'] ?? 'Gestures';
                $accuracy = (float) ($payload['job']['results']['best_val_accuracy'] ?? $payload['job']['results']['accuracy'] ?? 0);
                $modelPath = $payload['job']['results']['model_path'] ?? null;
                app(\App\Services\AdminNotificationService::class)->notifyTrainingComplete(basename($path), $category, $accuracy, $modelPath);
            } catch (\Throwable $e) {
                Log::warning('Training notification could not be recorded', ['exception_type' => get_class($e)]);
            }
        }

        return response()->json($payload, $upstream->status())->header('Cache-Control', 'no-store');
    }
}
