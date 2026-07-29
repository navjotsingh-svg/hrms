<?php

namespace App\Providers;

use App\Listeners\ApplyTestingMailBcc;
use App\Services\HrmsPageThemeService;
use App\Services\PublicUploadDirectoryService;
use App\Support\DateTimeLabel;
use Carbon\Carbon;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
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
        Event::listen(MessageSending::class, ApplyTestingMailBcc::class);

        Paginator::useBootstrapFive();

        Carbon::macro('labelStack', function (): string {
            return DateTimeLabel::format($this, '');
        });

        app(PublicUploadDirectoryService::class)->ensureBaseDirectories();

        View::composer('layouts.app', function ($view) {
            $theme = app(HrmsPageThemeService::class)->resolve(request()->route()?->getName());
            $view->with('hrmsPageTheme', $theme);
        });
    }
}
