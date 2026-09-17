<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Story;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminStoryController extends Controller
{
    public function index(): JsonResponse
    {
        $stories = Story::withCount(['unlocks', 'progress'])
            ->orderBy('order')
            ->orderBy('chapter_number')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $stories,
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'chapter_number' => ['nullable', 'integer', 'min:1'],
            'order' => ['nullable', 'integer', 'min:1'],
            'is_free' => ['nullable'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'episodes' => ['nullable', 'integer', 'min:1'],
            'icon' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:100'],
            'required_lesson_ids' => ['nullable'],
            'cover_photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'package_file' => ['nullable', 'file', 'max:102400'], // max 100MB
        ]);

        $maxChapter = Story::max('chapter_number') ?? 0;
        $chapterNumber = ! empty($validated['chapter_number']) ? (int) $validated['chapter_number'] : $maxChapter + 1;
        $order = ! empty($validated['order']) ? (int) $validated['order'] : $chapterNumber;

        $slugBase = Str::slug($validated['title']) ?: 'chapter-'.$chapterNumber;
        $slug = $slugBase;
        $counter = 1;
        while (Story::where('slug', $slug)->exists()) {
            $slug = $slugBase.'-'.$counter++;
        }

        $coverUrl = null;
        if ($request->hasFile('cover_photo')) {
            $file = $request->file('cover_photo');
            $dir = public_path('stories/covers');
            if (! File::isDirectory($dir)) {
                File::makeDirectory($dir, 0755, true);
            }
            $filename = 'cover_ch_'.$chapterNumber.'_'.time().'.'.$file->getClientOriginalExtension();
            $file->move($dir, $filename);
            $coverUrl = '/stories/covers/'.$filename;
        }

        $fileName = null;
        if ($request->hasFile('package_file')) {
            $file = $request->file('package_file');
            $dir = storage_path('app/stories');
            if (! File::isDirectory($dir)) {
                File::makeDirectory($dir, 0755, true);
            }
            $filename = 'chapter_'.$chapterNumber.'_'.time().'.pck';
            $file->move($dir, $filename);
            $fileName = $filename;
        }

        $requiredLessons = $request->input('required_lesson_ids');
        if (is_string($requiredLessons)) {
            $decoded = json_decode($requiredLessons, true);
            $requiredLessons = is_array($decoded) ? $decoded : array_filter(explode(',', $requiredLessons));
        }

        $isFree = filter_var($request->input('is_free', false), FILTER_VALIDATE_BOOLEAN);

        $story = Story::create([
            'slug' => $slug,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'chapter_number' => $chapterNumber,
            'order' => $order,
            'is_free' => $isFree,
            'price' => $isFree ? 0.00 : (isset($validated['price']) ? (float) $validated['price'] : 49.00),
            'currency' => $validated['currency'] ?? 'PHP',
            'episodes' => $validated['episodes'] ?? 3,
            'icon' => $validated['icon'] ?? 'MapPin',
            'color' => $validated['color'] ?? 'from-violet-500 to-fuchsia-500',
            'cover_url' => $coverUrl,
            'file_name' => $fileName,
            'required_lesson_ids' => $requiredLessons ?? [],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Story chapter created successfully.',
            'data' => $story,
        ], 201);
    }

    public function show(Story $story): JsonResponse
    {
        $story->loadCount(['unlocks', 'progress']);

        return response()->json([
            'success' => true,
            'data' => $story,
        ], 200);
    }

    public function update(Request $request, Story $story): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'chapter_number' => ['nullable', 'integer', 'min:1'],
            'order' => ['nullable', 'integer', 'min:1'],
            'is_free' => ['nullable'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'episodes' => ['nullable', 'integer', 'min:1'],
            'icon' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:100'],
            'required_lesson_ids' => ['nullable'],
            'cover_photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'package_file' => ['nullable', 'file', 'max:102400'],
        ]);

        if (isset($validated['title'])) {
            $story->title = $validated['title'];
        }

        if ($request->has('description')) {
            $story->description = $request->input('description');
        }

        if (! empty($validated['chapter_number'])) {
            $story->chapter_number = (int) $validated['chapter_number'];
        }

        if (! empty($validated['order'])) {
            $story->order = (int) $validated['order'];
        }

        if ($request->has('is_free')) {
            $story->is_free = filter_var($request->input('is_free'), FILTER_VALIDATE_BOOLEAN);
            if ($story->is_free) {
                $story->price = 0.00;
            }
        }

        if ($request->has('price') && ! $story->is_free) {
            $story->price = (float) $request->input('price');
        }

        if (! empty($validated['currency'])) {
            $story->currency = $validated['currency'];
        }

        if (! empty($validated['episodes'])) {
            $story->episodes = (int) $validated['episodes'];
        }

        if ($request->has('required_lesson_ids')) {
            $req = $request->input('required_lesson_ids');
            if (is_string($req)) {
                $decoded = json_decode($req, true);
                $req = is_array($decoded) ? $decoded : array_filter(explode(',', $req));
            }
            $story->required_lesson_ids = $req ?? [];
        }

        if ($request->hasFile('cover_photo')) {
            $file = $request->file('cover_photo');
            $dir = public_path('stories/covers');
            if (! File::isDirectory($dir)) {
                File::makeDirectory($dir, 0755, true);
            }
            $filename = 'cover_ch_'.$story->chapter_number.'_'.time().'.'.$file->getClientOriginalExtension();
            $file->move($dir, $filename);
            $story->cover_url = '/stories/covers/'.$filename;
        }

        if ($request->hasFile('package_file')) {
            $file = $request->file('package_file');
            $dir = storage_path('app/stories');
            if (! File::isDirectory($dir)) {
                File::makeDirectory($dir, 0755, true);
            }
            $filename = 'chapter_'.$story->chapter_number.'_'.time().'.pck';
            $file->move($dir, $filename);
            $story->file_name = $filename;
        }

        $story->save();

        return response()->json([
            'success' => true,
            'message' => 'Story chapter updated successfully.',
            'data' => $story,
        ], 200);
    }

    public function destroy(Story $story): JsonResponse
    {
        if ($story->chapter_number === 1) {
            throw ValidationException::withMessages([
                'story' => 'Chapter 1 (Prologue) is protected and cannot be deleted.',
            ]);
        }

        $story->delete();

        return response()->json([
            'success' => true,
            'message' => 'Story chapter deleted successfully.',
        ], 200);
    }
}
