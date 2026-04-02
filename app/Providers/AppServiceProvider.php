<?php

namespace App\Providers;

use App\Events\TaskCreated;
use App\Listeners\SendTaskNotification;
use App\Services\ImpersonationContext;
use App\Support\AuthEmailSupport;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ImpersonationContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Auth\Notifications\ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            $emailSupport = app(AuthEmailSupport::class);
            $locale = $emailSupport->resolveLocale($notifiable->locale ?? null, request()?->header('Accept-Language'));

            return $emailSupport->localizedFrontendPath(
                'auth/reset-password?token=' . urlencode($token) . '&email=' . urlencode($notifiable->getEmailForPasswordReset()),
                $locale
            );
        });

        Vite::prefetch(concurrency: 3);
        Event::listen(TaskCreated::class, SendTaskNotification::class);
    }
}
