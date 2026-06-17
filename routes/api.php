<?php
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GestureController;
use App\Http\Controllers\Api\LevelController;
use App\Http\Controllers\Api\ProgressController;
use App\Http\Controllers\Api\QuestController;
use App\Http\Controllers\Api\QuizController;
use App\Http\Controllers\Api\SignController;
use App\Http\Controllers\Api\UserQuestController;
use App\Http\Controllers\Api\AchievementController;
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
Route::get('/levels/{level}/quizzes', [QuizController::class, 'indexByLevel']);
Route::get('/signs/{sign}', [SignController::class, 'show']);
Route::get('/quests/{quest}', [QuestController::class, 'show']);
Route::get('/quizzes/{quiz}', [QuizController::class, 'show']);
Route::get('/achievements', [AchievementController::class, 'index']);

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

    Route::post('/user/quizzes/{quiz}/submit', [QuizController::class, 'submit']);

    Route::prefix('user/achievements')->group(function () {
        Route::get('/', [AchievementController::class, 'userAchievements']);
        Route::post('/check', [AchievementController::class, 'check']);
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

        // Quizzes
        Route::post('/quizzes', [QuizController::class, 'store']);
        Route::put('/quizzes/{quiz}', [QuizController::class, 'update']);
        Route::delete('/quizzes/{quiz}', [QuizController::class, 'destroy']);

        // Quiz questions
        Route::post('/quizzes/{quiz}/questions', [QuizController::class, 'addQuestion']);
        Route::put('/questions/{question}', [QuizController::class, 'updateQuestion']);
        Route::delete('/questions/{question}', [QuizController::class, 'deleteQuestion']);

        // Question choices
        Route::post('/questions/{question}/choices', [QuizController::class, 'addChoice']);
        Route::put('/choices/{choice}', [QuizController::class, 'updateChoice']);
        Route::delete('/choices/{choice}', [QuizController::class, 'deleteChoice']);

        // Admin dashboard
        Route::get('/analytics', [AdminController::class, 'analytics']);
        Route::get('/logs', [AdminController::class, 'logs']);

        // User management
        Route::get('/users', [AdminController::class, 'users']);
        Route::get('/users/{user}', [AdminController::class, 'showUser']);
        Route::patch('/users/{user}/activate', [AdminController::class, 'activateUser']);
        Route::patch('/users/{user}/deactivate', [AdminController::class, 'deactivateUser']);
    });
});
