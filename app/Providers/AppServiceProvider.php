<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use App\Events\TaskCreated;
use App\Listeners\SendTaskNotification;
use App\Events\BoatCreated;
use App\Events\BoatStatusActivated;
use App\Events\BookingCreated;
use App\Events\BookingCompleted;
use App\Events\BidAccepted;
use App\Events\DepositPaid;
use App\Events\UserRegistered;
use App\Events\HarborCreated;
use App\Events\ContractSigned;
use App\Events\EscrowFunded;
use App\Events\EscrowReleased;
use App\Listeners\ScheduleAutomatedTasks;
use App\Models\Deal;
use App\Models\SystemLog;
use App\Security\ActionSecurityRegistry;
use Laravel\Sanctum\Sanctum;

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
        Sanctum::usePersonalAccessTokenModel(\App\Models\PersonalAccessToken::class);
        \App\Models\Yacht::observe(\App\Observers\YachtObserver::class);
        \App\Models\Harbor::observe(\App\Observers\HarborObserver::class);
        SystemLog::observe(\App\Observers\SystemLogObserver::class);

        Event::listen(TaskCreated::class, SendTaskNotification::class);

        Event::listen(BoatCreated::class, ScheduleAutomatedTasks::class);
        Event::listen(BoatStatusActivated::class, ScheduleAutomatedTasks::class);
        Event::listen(BookingCreated::class, ScheduleAutomatedTasks::class);
        Event::listen(BookingCompleted::class, ScheduleAutomatedTasks::class);
        Event::listen(BidAccepted::class, ScheduleAutomatedTasks::class);
        Event::listen(DepositPaid::class, ScheduleAutomatedTasks::class);
        Event::listen(UserRegistered::class, ScheduleAutomatedTasks::class);
        Event::listen(HarborCreated::class, ScheduleAutomatedTasks::class);
        Event::listen(ContractSigned::class, ScheduleAutomatedTasks::class);
        Event::listen(EscrowFunded::class, ScheduleAutomatedTasks::class);
        Event::listen(EscrowReleased::class, ScheduleAutomatedTasks::class);

        ActionSecurityRegistry::define(
            'deal.contract.generate',
            level: 'high',
            fresh: 30,
            audit: true,
            idempotency: true,
            snapshot: true,
            model: Deal::class,
            routeParam: 'dealId'
        );
        ActionSecurityRegistry::define(
            'deal.signhost.create',
            level: 'high',
            fresh: 30,
            audit: true,
            idempotency: true,
            snapshot: true,
            model: Deal::class,
            routeParam: 'dealId',
            with: ['signhostTransactions']
        );
        ActionSecurityRegistry::define(
            'deal.payments.deposit.create',
            level: 'high',
            fresh: 15,
            audit: true,
            idempotency: true,
            snapshot: true,
            model: Deal::class,
            routeParam: 'dealId',
            with: ['payments']
        );
        ActionSecurityRegistry::define(
            'deal.payments.platform_fee.create',
            level: 'high',
            fresh: 15,
            audit: true,
            idempotency: true,
            snapshot: true,
            model: Deal::class,
            routeParam: 'dealId',
            with: ['payments']
        );
        ActionSecurityRegistry::define(
            'wallet.topup.create',
            level: 'high',
            fresh: 15,
            audit: true,
            idempotency: true
        );
    }
}
