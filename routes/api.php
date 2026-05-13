<?php

use App\Http\Controllers\Api\AdminMetricsController;
use App\Http\Controllers\Api\AdminReportController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AvatarController;
use App\Http\Controllers\Api\CareLogController;
use App\Http\Controllers\Api\CareScheduleController;
use App\Http\Controllers\Api\CareSettingController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FeedController;
use App\Http\Controllers\Api\FollowController;
use App\Http\Controllers\Api\LikeController;
use App\Http\Controllers\Api\PlantController;
use App\Http\Controllers\Api\PlantImageController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\TipController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// ============================================================================
// PUBLIC ROUTES (Authentication)
// ============================================================================

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

// ============================================================================
// PUBLIC READ-ONLY ROUTES
// ============================================================================

Route::get('/plants/public', [PlantController::class, 'public']);
Route::get('/plants/{plantId}/tips', [TipController::class, 'index']);
Route::get('/plants/{plantId}/likes/count', [LikeController::class, 'count']);
Route::get('/feed', [FeedController::class, 'index']);
Route::get('/feed/trending', [FeedController::class, 'trending']);
Route::get('/feed/user/{userId}', [FeedController::class, 'userPlants']);
Route::get('/feed/with-tips', [FeedController::class, 'withTips']);

// ============================================================================
// PROTECTED ROUTES (Require Authentication)
// ============================================================================

Route::middleware('auth:sanctum')->group(function () {

    // ========================================================================
    // AUTHENTICATION
    // ========================================================================

    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);

    // ========================================================================
    // USERS
    // ========================================================================

    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::put('/users/profile', [UserController::class, 'update']);
    Route::get('/users/{id}/avatar', [AvatarController::class, 'show']);
    Route::post('/users/profile/avatar', [AvatarController::class, 'update']);
    Route::delete('/users/profile/avatar', [AvatarController::class, 'destroy']);

    // Admin only routes
    Route::middleware(['admin', 'throttle:admin-actions'])->group(function () {
        Route::delete('/users/{id}', [UserController::class, 'destroy']);
        Route::put('/users/{id}/role', [UserController::class, 'updateRole']);
        Route::delete('/users/{id}/avatar', [AvatarController::class, 'destroyForUser']);

        Route::get('/admin/reports', [AdminReportController::class, 'index']);
        Route::get('/admin/reports/{id}', [AdminReportController::class, 'show']);
        Route::put('/admin/reports/{id}/review', [AdminReportController::class, 'review']);
        Route::get('/admin/metrics/traffic', [AdminMetricsController::class, 'traffic']);
    });

    // ========================================================================
    // ROOMS
    // ========================================================================

    Route::get('/rooms', [RoomController::class, 'index']);
    Route::post('/rooms', [RoomController::class, 'store']);
    Route::get('/rooms/{id}', [RoomController::class, 'show']);
    Route::put('/rooms/{id}', [RoomController::class, 'update']);
    Route::delete('/rooms/{id}', [RoomController::class, 'destroy']);

    // ========================================================================
    // PLANTS
    // ========================================================================

    Route::get('/plants', [PlantController::class, 'index']);
    Route::post('/plants', [PlantController::class, 'store']);
    Route::get('/plants/todays-care', [PlantController::class, 'todaysCare']);
    Route::get('/plants/{id}', [PlantController::class, 'show']);
    Route::put('/plants/{id}', [PlantController::class, 'update']);
    Route::delete('/plants/{id}', [PlantController::class, 'destroy']);
    Route::post('/plants/{id}/toggle-public', [PlantController::class, 'togglePublic']);
    Route::get('/plants/{id}/schedule', [PlantController::class, 'schedule']);
    Route::get('/rooms/{roomId}/plants', [PlantController::class, 'byRoom']);
    Route::get('/plants/{plantId}/images', [PlantImageController::class, 'index']);
    Route::post('/plants/{plantId}/images', [PlantImageController::class, 'store']);
    Route::get('/plant-images/{id}', [PlantImageController::class, 'show']);
    Route::put('/plant-images/{id}', [PlantImageController::class, 'update']);
    Route::delete('/plant-images/{id}', [PlantImageController::class, 'destroy']);

    // ========================================================================
    // CARE SETTINGS (Настройки ухода)
    // ========================================================================

    Route::get('/plants/{plantId}/care-settings', [CareSettingController::class, 'index']);
    Route::post('/plants/{plantId}/care-settings', [CareSettingController::class, 'store']);
    Route::put('/care-settings/{id}', [CareSettingController::class, 'update']);
    Route::delete('/care-settings/{id}', [CareSettingController::class, 'destroy']);
    Route::post('/care-settings/{id}/toggle', [CareSettingController::class, 'toggle']);

    // ========================================================================
    // CARE LOGS (История ухода)
    // ========================================================================

    Route::get('/plants/{plantId}/care-logs', [CareLogController::class, 'index']);
    Route::post('/plants/{plantId}/care-logs', [CareLogController::class, 'store']);
    Route::get('/care-logs/{id}', [CareLogController::class, 'show']);
    Route::delete('/care-logs/{id}', [CareLogController::class, 'destroy']);

    // ========================================================================
    // CARE SCHEDULE (Расписание ухода)
    // ========================================================================

    Route::get('/care-schedule/plant/{plantId}', [CareScheduleController::class, 'plantSchedule']);
    Route::get('/care-schedule/todays-care', [CareScheduleController::class, 'todaysCare']);
    Route::get('/care-schedule/month', [CareScheduleController::class, 'monthSchedule']);
    Route::get('/care-schedule/upcoming', [CareScheduleController::class, 'upcomingCare']);
    Route::get('/care-schedule/overdue', [CareScheduleController::class, 'overdueCare']);

    // ========================================================================
    // TIPS (Советы)
    // ========================================================================

    Route::get('/tips/my', [TipController::class, 'myTips']);
    Route::get('/tips/received', [TipController::class, 'receivedTips']);
    Route::get('/tips/stats', [TipController::class, 'tipStats']);
    Route::get('/tips/received/{status}', [TipController::class, 'receivedTipsByStatus']);
    Route::post('/plants/{plantId}/tips', [TipController::class, 'store']);
    Route::get('/tips/{id}', [TipController::class, 'show']);
    Route::put('/tips/{id}/status', [TipController::class, 'updateStatus']);
    Route::delete('/tips/{id}', [TipController::class, 'destroy']);

    Route::post('/plants/{plantId}/reports', [ReportController::class, 'reportPlant']);
    Route::post('/tips/{tipId}/reports', [ReportController::class, 'reportTip']);

    // ========================================================================
    // LIKES
    // ========================================================================

    Route::post('/plants/{plantId}/like', [LikeController::class, 'toggle']);
    Route::get('/plants/{plantId}/likes', [LikeController::class, 'index']);
    Route::get('/plants/{plantId}/likes/is-liked', [LikeController::class, 'isLiked']);
    Route::get('/likes/my', [LikeController::class, 'myLikes']);

    // ========================================================================
    // FOLLOWS (Подписки)
    // ========================================================================

    Route::post('/users/{userId}/follow', [FollowController::class, 'follow']);
    Route::delete('/users/{userId}/unfollow', [FollowController::class, 'unfollow']);
    Route::get('/users/{userId}/followers', [FollowController::class, 'followers']);
    Route::get('/users/{userId}/followers/count', [FollowController::class, 'followerCount']);
    Route::get('/users/{userId}/following', [FollowController::class, 'following']);
    Route::get('/users/{userId}/following/count', [FollowController::class, 'followingCount']);
    Route::get('/users/{userId}/is-following', [FollowController::class, 'isFollowing']);
    Route::get('/users/{userId}/relationship', [FollowController::class, 'checkRelationship']);

    // ========================================================================
    // FEED (Лента)
    // ========================================================================

    Route::get('/feed/personal', [FeedController::class, 'personal']);
    Route::get('/feed/recommendations', [FeedController::class, 'recommendations']);
    Route::get('/feed/liked', [FeedController::class, 'likedPlants']);

    // ========================================================================
    // DASHBOARD (Аналитика и статистика)
    // ========================================================================

    Route::get('/dashboard/overview', [DashboardController::class, 'overview']);
    Route::get('/dashboard/activity', [DashboardController::class, 'activityStats']);
    Route::get('/dashboard/plant-health', [DashboardController::class, 'plantHealthStats']);

});
