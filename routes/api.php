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
use App\Http\Controllers\Api\HeartController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\StoryController;
use App\Http\Controllers\Api\AdminStoryController;
use App\Http\Controllers\Api\FinisherController;
use App\Http\Controllers\Api\UserActivityController;
use App\Http\Controllers\Api\CurriculumEventController;
use App\Http\Controllers\Api\RefundController;
use App\Http\Controllers\Api\AdminRefundController;
use App\Http\Controllers\Api\SupportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('/send-code', [AuthController::class, 'sendVerificationCode'])->middleware('throttle:5,1');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/forgot-password', [AuthController::class, 'sendPasswordResetCode'])->middleware('throttle:5,1');
    Route::post('/verify-reset-code', [AuthController::class, 'verifyPasswordResetCode'])->middleware('throttle:10,1');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');
    Route::post('/google', [AuthController::class, 'google'])->middleware('throttle:10,1');
});
Route::post('/payments/webhook', [PaymentController::class, 'webhook']);
Route::post('/contact', [SupportController::class, 'sendPublicContact'])->middleware('throttle:5,1');

// Public game data (read-only)
Route::get('/levels', [LevelController::class, 'index']);
Route::get('/levels/{level}', [LevelController::class, 'show']);
Route::get('/levels/{level}/signs', [SignController::class, 'index']);
Route::get('/levels/{level}/quests', [QuestController::class, 'index']);
Route::get('/levels/{level}/quizzes', [QuizController::class, 'indexByLevel']);
Route::get('/signs', [SignController::class, 'all']);
Route::get('/signs/{sign}', [SignController::class, 'show']);
Route::get('/quests/{quest}', [QuestController::class, 'show']);
Route::get('/quizzes/{quiz}', [QuizController::class, 'show']);
Route::get('/achievements', [AchievementController::class, 'index']);
Route::get('/stories', [StoryController::class, 'index']);
Route::get('/stories/package', [StoryController::class, 'package']);
Route::get('/stories/{story}', [StoryController::class, 'show']);
Route::post('/stories/verify-ticket', [StoryController::class, 'verifyTicket']);
Route::get('/finishers', [FinisherController::class, 'index']);
Route::get('/curriculum/events', [CurriculumEventController::class, 'index']);
Route::get('/platform/maintenance', [AdminController::class, 'getMaintenance']);

/*
|--------------------------------------------------------------------------
| Protected Routes (Authenticated Users)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'active'])->group(function () {

    // Auth
    Route::prefix('auth')->group(function () {
        Route::get('/preferences', [\App\Http\Controllers\Api\PreferenceController::class, 'show']);
        Route::patch('/preferences', [\App\Http\Controllers\Api\PreferenceController::class, 'update'])->middleware('throttle:30,1');
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
    });

    // User profile & quest progress
    Route::prefix('user')->group(function () {
        Route::put('/profile', [AuthController::class, 'updateProfile']);
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
    Route::post('/user/quizzes/{quiz}/questions/{question}/submit', [QuizController::class, 'submitQuestion']);

    Route::prefix('user/achievements')->group(function () {
        Route::get('/', [AchievementController::class, 'userAchievements']);
        Route::post('/check', [AchievementController::class, 'check']);
    });

    Route::prefix('user')->group(function () {
        Route::get('/activities', [UserActivityController::class, 'index']);
        Route::get('/hearts', [HeartController::class, 'status']);
        Route::get('/hearts/transactions', [HeartController::class, 'transactions']);
        Route::post('/hearts/refill', [HeartController::class, 'refill']);

        Route::post('/purchases/checkout', [PurchaseController::class, 'checkout']);
        Route::post('/purchases', [PurchaseController::class, 'store']);
        Route::get('/purchases', [PurchaseController::class, 'userPurchases']);
        Route::get('/purchases/{purchase}', [PurchaseController::class, 'show']);
        Route::post('/purchases/{purchase}/verify', [PurchaseController::class, 'verify']);
        Route::post('/purchases/{purchase}/refund-request', [RefundController::class, 'store']);
        Route::get('/refund-requests', [RefundController::class, 'userIndex']);

        Route::post('/stories/{story}/checkout', [StoryController::class, 'checkout']);
        Route::post('/stories/{story}/complete', [StoryController::class, 'complete']);
        Route::post('/stories/{story}/launch-ticket', [StoryController::class, 'launchTicket']);

        Route::post('/support/message', [SupportController::class, 'sendMessage'])->middleware('throttle:10,1');

        Route::get('/notifications', [\App\Http\Controllers\Api\PlayerNotificationController::class, 'index']);
        Route::post('/notifications/read', [\App\Http\Controllers\Api\PlayerNotificationController::class, 'markAsRead']);
        Route::post('/notifications/read-all', [\App\Http\Controllers\Api\PlayerNotificationController::class, 'markAllAsRead']);
        Route::delete('/notifications/{id}', [\App\Http\Controllers\Api\PlayerNotificationController::class, 'destroy']);
        Route::post('/notifications/clear-all', [\App\Http\Controllers\Api\PlayerNotificationController::class, 'clearAll']);
    });

    /*
    |----------------------------------------------------------------------
    | Admin Routes (Admin Only)
    |----------------------------------------------------------------------
    */
    Route::middleware('admin')->prefix('admin')->group(function () {

        // Levels
        Route::get('/levels/archived', [LevelController::class, 'archived']);
        Route::post('/levels/reorder', [LevelController::class, 'reorder']);
        Route::post('/levels/{level}/archive', [LevelController::class, 'archive']);
        Route::post('/levels/{id}/restore', [LevelController::class, 'restore']);
        Route::delete('/levels/{id}/force', [LevelController::class, 'forceDestroy']);
        Route::post('/levels', [LevelController::class, 'store']);
        Route::put('/levels/{level}', [LevelController::class, 'update']);
        Route::delete('/levels/{level}', [LevelController::class, 'destroy']);

        // Signs
        Route::get('/signs/archived', [SignController::class, 'archived']);
        Route::post('/signs/reorder', [SignController::class, 'reorder']);
        Route::post('/signs/bulk-archive', [SignController::class, 'bulkArchive']);
        Route::post('/signs/bulk-restore', [SignController::class, 'bulkRestore']);
        Route::post('/signs/bulk-force-delete', [SignController::class, 'bulkForceDestroy']);
        Route::post('/signs/convert-video', [SignController::class, 'convertVideo']);
        Route::post('/signs/{sign}/archive', [SignController::class, 'archive']);
        Route::post('/signs/{id}/restore', [SignController::class, 'restore']);
        Route::delete('/signs/{id}/force', [SignController::class, 'forceDestroy']);
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
        Route::post('/platform/maintenance', [AdminController::class, 'updateMaintenance']);

        // User management
        Route::get('/users', [AdminController::class, 'users']);
        Route::get('/users/{user}', [AdminController::class, 'showUser']);
        Route::match(['patch', 'post'], '/users/{user}/activate', [AdminController::class, 'activateUser']);
        Route::match(['patch', 'post'], '/users/{user}/deactivate', [AdminController::class, 'deactivateUser']);

        Route::get('/purchases', [PurchaseController::class, 'adminPurchases']);
        Route::get('/revenue/summary', [PurchaseController::class, 'revenueSummary']);
        Route::post('/purchases/{purchase}/mark-paid', [PurchaseController::class, 'markPaid']);

        Route::get('/heart-transactions', [HeartController::class, 'adminTransactions']);
        Route::post('/hearts/grant-bulk', [HeartController::class, 'grantBulk']);
        Route::post('/users/{user}/hearts/grant', [HeartController::class, 'grant']);

        // Refund Requests Management
        Route::get('/refund-requests', [AdminRefundController::class, 'index']);
        Route::post('/refund-requests/{refundRequest}/approve', [AdminRefundController::class, 'approve']);
        Route::post('/refund-requests/{refundRequest}/reject', [AdminRefundController::class, 'reject']);

        // Story Chapters (Admin Dynamic Story Management)
        Route::get('/stories/archived', [AdminStoryController::class, 'archived']);
        Route::post('/stories/bulk-archive', [AdminStoryController::class, 'bulkArchive']);
        Route::post('/stories/bulk-restore', [AdminStoryController::class, 'bulkRestore']);
        Route::post('/stories/bulk-force-delete', [AdminStoryController::class, 'bulkForceDestroy']);
        Route::post('/stories/{story}/archive', [AdminStoryController::class, 'archive']);
        Route::post('/stories/{id}/restore', [AdminStoryController::class, 'restore']);
        Route::delete('/stories/{id}/force', [AdminStoryController::class, 'forceDestroy']);
        Route::get('/stories', [AdminStoryController::class, 'index']);
        Route::post('/stories', [AdminStoryController::class, 'store']);
        Route::get('/stories/{story}', [AdminStoryController::class, 'show']);
        Route::post('/stories/{story}', [AdminStoryController::class, 'update']);
        Route::put('/stories/{story}', [AdminStoryController::class, 'update']);
        Route::delete('/stories/{story}', [AdminStoryController::class, 'destroy']);
    });
});
