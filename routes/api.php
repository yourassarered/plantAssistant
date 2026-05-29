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

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::get('/plants/public', [PlantController::class, 'public']);
Route::get('/plants/public/{id}', [PlantController::class, 'publicShow']);
Route::get('/plants/public/{plantId}/images', [PlantImageController::class, 'index']);
Route::get('/plants/{plantId}/tips', [TipController::class, 'index']);
Route::get('/plants/{plantId}/likes/count', [LikeController::class, 'count']);
Route::get('/feed', [FeedController::class, 'index']);
Route::get('/feed/trending', [FeedController::class, 'trending']);
Route::get('/feed/user/{userId}', [FeedController::class, 'userPlants']);
Route::get('/feed/with-tips', [FeedController::class, 'withTips']);
Route::get('/users/{id}', [UserController::class, 'show']);
Route::get('/users/{userId}/followers/count', [FollowController::class, 'followerCount']);
Route::get('/users/{userId}/following/count', [FollowController::class, 'followingCount']);

Route::middleware(['auth:sanctum', 'not_blocked'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);

    Route::get('/users', [UserController::class, 'index']);
    Route::put('/users/profile', [UserController::class, 'update']);
    Route::get('/users/{id}/avatar', [AvatarController::class, 'show']);
    Route::post('/users/profile/avatar', [AvatarController::class, 'update']);
    Route::delete('/users/profile/avatar', [AvatarController::class, 'destroy']);

    Route::middleware(['admin', 'throttle:admin-actions'])->group(function () {
        Route::delete('/users/{id}', [UserController::class, 'destroy']);
        Route::put('/users/{id}', [UserController::class, 'adminUpdate']);
        Route::post('/users/{id}/block', [UserController::class, 'block']);
        Route::post('/users/{id}/unblock', [UserController::class, 'unblock']);
        Route::put('/users/{id}/role', [UserController::class, 'updateRole']);
        Route::delete('/users/{id}/avatar', [AvatarController::class, 'destroyForUser']);

        Route::get('/admin/reports', [AdminReportController::class, 'index']);
        Route::get('/admin/reports/{id}', [AdminReportController::class, 'show']);
        Route::put('/admin/reports/{id}/review', [AdminReportController::class, 'review']);
        Route::post('/admin/plants/{plantId}/moderate', [AdminReportController::class, 'moderatePlant']);
        Route::get('/admin/metrics/traffic', [AdminMetricsController::class, 'traffic']);
    });

    Route::get('/rooms', [RoomController::class, 'index']);
    Route::post('/rooms', [RoomController::class, 'store']);
    Route::get('/rooms/{id}', [RoomController::class, 'show']);
    Route::put('/rooms/{id}', [RoomController::class, 'update']);
    Route::delete('/rooms/{id}', [RoomController::class, 'destroy']);

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

    Route::get('/plants/{plantId}/care-settings', [CareSettingController::class, 'index']);
    Route::post('/plants/{plantId}/care-settings', [CareSettingController::class, 'store']);
    Route::put('/care-settings/{id}', [CareSettingController::class, 'update']);
    Route::delete('/care-settings/{id}', [CareSettingController::class, 'destroy']);
    Route::post('/care-settings/{id}/toggle', [CareSettingController::class, 'toggle']);

    Route::get('/plants/{plantId}/care-logs', [CareLogController::class, 'index']);
    Route::post('/plants/{plantId}/care-logs', [CareLogController::class, 'store']);
    Route::get('/care-logs/{id}', [CareLogController::class, 'show']);
    Route::delete('/care-logs/{id}', [CareLogController::class, 'destroy']);

    Route::get('/care-schedule/plant/{plantId}', [CareScheduleController::class, 'plantSchedule']);
    Route::get('/care-schedule/todays-care', [CareScheduleController::class, 'todaysCare']);
    Route::get('/care-schedule/month', [CareScheduleController::class, 'monthSchedule']);
    Route::get('/care-schedule/upcoming', [CareScheduleController::class, 'upcomingCare']);
    Route::get('/care-schedule/overdue', [CareScheduleController::class, 'overdueCare']);

    Route::get('/tips/my', [TipController::class, 'myTips']);
    Route::get('/tips/received', [TipController::class, 'receivedTips']);
    Route::get('/tips/stats', [TipController::class, 'tipStats']);
    Route::get('/tips/received/{status}', [TipController::class, 'receivedTipsByStatus']);
    Route::post('/plants/{plantId}/tips', [TipController::class, 'store']);
    Route::get('/tips/{id}', [TipController::class, 'show']);
    Route::put('/tips/{id}/status', [TipController::class, 'updateStatus']);
    Route::delete('/tips/{id}', [TipController::class, 'destroy']);

    Route::post('/plants/{plantId}/reports', [ReportController::class, 'reportPlant']);
    Route::get('/plants/{plantId}/reports', [ReportController::class, 'plantReports']);
    Route::post('/tips/{tipId}/reports', [ReportController::class, 'reportTip']);
    Route::get('/reports/my', [ReportController::class, 'myReports']);
    Route::get('/reports/received', [ReportController::class, 'receivedReports']);

    Route::post('/plants/{plantId}/like', [LikeController::class, 'toggle']);
    Route::get('/plants/{plantId}/likes', [LikeController::class, 'index']);
    Route::get('/plants/{plantId}/likes/is-liked', [LikeController::class, 'isLiked']);
    Route::get('/likes/my', [LikeController::class, 'myLikes']);
    Route::get('/likes/states', [LikeController::class, 'states']);

    Route::post('/users/{userId}/follow', [FollowController::class, 'follow']);
    Route::delete('/users/{userId}/unfollow', [FollowController::class, 'unfollow']);
    Route::get('/users/{userId}/followers', [FollowController::class, 'followers']);
    Route::get('/users/{userId}/following', [FollowController::class, 'following']);
    Route::get('/users/{userId}/is-following', [FollowController::class, 'isFollowing']);
    Route::get('/users/{userId}/relationship', [FollowController::class, 'checkRelationship']);

    Route::get('/feed/personal', [FeedController::class, 'personal']);
    Route::get('/feed/recommendations', [FeedController::class, 'recommendations']);
    Route::get('/feed/liked', [FeedController::class, 'likedPlants']);

    Route::get('/dashboard/overview', [DashboardController::class, 'overview']);
    Route::get('/dashboard/activity', [DashboardController::class, 'activityStats']);
    Route::get('/dashboard/plant-health', [DashboardController::class, 'plantHealthStats']);
});
