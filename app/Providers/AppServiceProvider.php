<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use App\Models\FixedAsset;
use App\Observers\FixedAssetObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Event::listen(function (\SocialiteProviders\Manager\SocialiteWasCalled $event) {
			$event->extendSocialite('laravelpassport', \SocialiteProviders\LaravelPassport\Provider::class);
		});
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 告訴 Laravel：當 FixedAsset 有變動時，請通知 FixedAssetObserver
		FixedAsset::observe(FixedAssetObserver::class);
    }
}
