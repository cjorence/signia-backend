<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PurchaseResource;
use App\Models\Story;
use App\Services\PaymentService;
use App\Services\StoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StoryController extends Controller
{
    public function __construct(
        protected StoryService $storyService,
        protected PaymentService $paymentService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user('sanctum') ?? Auth::user();
        $stories = $this->storyService->getStoriesForUser($user);

        return response()->json([
            'success' => true,
            'data' => $stories,
        ], 200);
    }

    public function show(Request $request, Story $story): JsonResponse
    {
        $user = $request->user('sanctum') ?? Auth::user();
        $stories = $this->storyService->getStoriesForUser($user);
        $item = $stories->firstWhere('id', $story->id) ?? [
            'id' => $story->id,
            'slug' => $story->slug,
            'title' => $story->title,
            'description' => $story->description,
            'chapter_number' => $story->chapter_number,
            'order' => $story->order,
            'is_free' => (bool) $story->is_free || $story->chapter_number === 1,
            'price' => (float) $story->price,
            'currency' => $story->currency ?? 'PHP',
            'episodes' => $story->episodes,
            'icon' => $story->icon,
            'color' => $story->color,
            'cover_url' => $story->cover_url,
            'file_name' => $story->file_name,
            'required_lesson_ids' => $story->required_lesson_ids ?? [],
            'is_unlocked' => $story->isUnlockedFor($user),
            'is_completed' => $story->isCompletedFor($user),
        ];

        return response()->json([
            'success' => true,
            'data' => $item,
        ], 200);
    }

    public function checkout(Request $request, Story $story): JsonResponse
    {
        $user = Auth::user();

        $purchase = $this->storyService->createStoryPurchase($user, $story);
        $checkout = $this->paymentService->createCheckoutSession($purchase);

        return response()->json([
            'success' => true,
            'message' => 'Story checkout session created successfully.',
            'data' => [
                'purchase' => new PurchaseResource($checkout['purchase']),
                'checkout_url' => $checkout['checkout_url'],
            ],
        ], 201);
    }

    public function complete(Request $request, Story $story): JsonResponse
    {
        $user = Auth::user();
        $result = $this->storyService->completeStory($user, $story);

        return response()->json([
            'success' => true,
            'message' => 'Story chapter marked as completed.',
            'data' => $result,
        ], 200);
    }

    public function launchTicket(Request $request, Story $story): JsonResponse
    {
        $user = Auth::user();
        $ticketData = $this->storyService->createLaunchTicket($user, $story);

        return response()->json([
            'success' => true,
            'message' => 'Launch ticket generated successfully.',
            'data' => $ticketData,
        ], 200);
    }

    public function verifyTicket(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ticket' => ['required', 'string'],
            'chapter' => ['required'],
        ]);

        $verification = $this->storyService->verifyLaunchTicket(
            $validated['ticket'],
            $validated['chapter']
        );

        return response()->json([
            'success' => true,
            'message' => 'Launch ticket verified.',
            'data' => $verification,
        ], 200);
    }

    public function package(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse|JsonResponse
    {
        $validated = $request->validate([
            'ticket' => ['required', 'string'],
            'chapter' => ['required'],
        ]);

        try {
            $packageInfo = $this->storyService->resolveAuthorizedPackage(
                $validated['ticket'],
                $validated['chapter']
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 403);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to serve package: ' . $e->getMessage(),
            ], 403);
        }

        return response()->file($packageInfo['file_path'], [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . $packageInfo['file_name'] . '"',
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
        ]);
    }
}
