<?php

namespace App\Providers;

use App\Models\CareLog;
use App\Models\CareSetting;
use App\Models\Follow;
use App\Models\Like;
use App\Models\Plant;
use App\Models\PlantImage;
use App\Models\Report;
use App\Models\Room;
use App\Models\Tip;
use App\Models\User;
use App\Observers\ReadModelCacheObserver;
use App\Policies\CareLogPolicy;
use App\Policies\CareSettingPolicy;
use App\Policies\FollowPolicy;
use App\Policies\LikePolicy;
use App\Policies\PlantImagePolicy;
use App\Policies\PlantPolicy;
use App\Policies\ReportPolicy;
use App\Policies\RoomPolicy;
use App\Policies\TipPolicy;
use App\Policies\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('admin-actions', function (Request $request) {
            $key = $request->user()?->id ? 'admin:'.$request->user()->id : 'ip:'.$request->ip();

            return [
                Limit::perMinute(30)->by($key),
                Limit::perHour(600)->by($key),
            ];
        });

        Gate::policy(Plant::class, PlantPolicy::class);
        Gate::policy(Tip::class, TipPolicy::class);
        Gate::policy(PlantImage::class, PlantImagePolicy::class);
        Gate::policy(Report::class, ReportPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Room::class, RoomPolicy::class);
        Gate::policy(CareSetting::class, CareSettingPolicy::class);
        Gate::policy(CareLog::class, CareLogPolicy::class);
        Gate::policy(Like::class, LikePolicy::class);
        Gate::policy(Follow::class, FollowPolicy::class);

        Plant::observe(ReadModelCacheObserver::class);
        Like::observe(ReadModelCacheObserver::class);
        Tip::observe(ReadModelCacheObserver::class);
        Follow::observe(ReadModelCacheObserver::class);
        Room::observe(ReadModelCacheObserver::class);
        CareLog::observe(ReadModelCacheObserver::class);
        CareSetting::observe(ReadModelCacheObserver::class);
        PlantImage::observe(ReadModelCacheObserver::class);
        Report::observe(ReadModelCacheObserver::class);
    }
}
