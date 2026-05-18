<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LevelController;
use App\Http\Controllers\Api\SignController;
use App\Http\Controllers\Api\QuestController;
use App\Http\Controllers\Api\UserQuestController;
use App\Http\Controllers\Api\ProgressController;
use App\Http\Controllers\Api\GestureController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Public game data (read-only)
Route::get('/levels', [LevelController::class, 'index']);
Route::get('/levels/{level}', [LevelController::class, 'show']);
Route::get('/levels/{level}/signs', [SignController::class, 'index']);
Route::get('/levels/{level}/quests', [QuestController::class, 'index']);
Route::get('/signs/{sign}', [SignController::class, 'show']);
Route::get('/quests/{quest}', [QuestController::class, 'show']);

/*
|--------------------------------------------------------------------------
| Protected Routes (Authenticated Users)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });

    // User quest progress
    Route::prefix('user')->group(function () {
        Route::get('/quests', [UserQuestController::class, 'index']);
        Route::post('/quests', [UserQuestController::class, 'updateStatus']);
    });

        // Progress tracking (user-only, scoped via Auth::id() in service)
    Route::prefix('user/progress')->group(function () {
        Route::get('/', [ProgressController::class, 'index']);
        Route::get('/level/{levelId}', [ProgressController::class, 'byLevel']);
        Route::get('/sign/{signId}', [ProgressController::class, 'bySign']);
        Route::post('/', [ProgressController::class, 'update']);
    });

        // Gesture logging (user-only, scoped via Auth::id() in service)
    Route::prefix('user/gestures')->group(function () {
        Route::get('/', [GestureController::class, 'index']);
        Route::get('/accuracy', [GestureController::class, 'accuracy']);
        Route::post('/', [GestureController::class, 'store']);
    });

    /*
    |----------------------------------------------------------------------
    | Admin Routes (Admin Only)
    |----------------------------------------------------------------------
    */
    Route::middleware('admin')->prefix('admin')->group(function () {

        // Levels
        Route::post('/levels', [LevelController::class, 'store']);
        Route::put('/levels/{level}', [LevelController::class, 'update']);
        Route::delete('/levels/{level}', [LevelController::class, 'destroy']);

        // Signs
        Route::post('/signs', [SignController::class, 'store']);
        Route::put('/signs/{sign}', [SignController::class, 'update']);
        Route::delete('/signs/{sign}', [SignController::class, 'destroy']);

        // Quests
        Route::post('/quests', [QuestController::class, 'store']);
        Route::put('/quests/{quest}', [QuestController::class, 'update']);
        Route::delete('/quests/{quest}', [QuestController::class, 'destroy']);
    });
});