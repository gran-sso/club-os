<?php

namespace App\Providers;

use App\Domain\Shared\Services\AuditLogService;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Domain\Integrations\Contracts\AccountingConnectorInterface::class,
            function () {
                $driver = \App\Domain\Shared\Models\SystemSetting::get('tally_driver', app()->environment('production') ? 'live' : 'simulator');

                if ($driver === 'live') {
                    return app(\App\Domain\Integrations\Adapters\TallyPrimeHttpConnector::class);
                }

                return app(\App\Domain\Integrations\Adapters\SimulatorTallyConnector::class);
            }
        );

        $this->app->bind(
            \App\Domain\Integrations\Contracts\PaymentProviderInterface::class,
            \App\Domain\Integrations\Adapters\ManualVerifiedPaymentProvider::class
        );

        $this->app->bind(
            \App\Domain\Integrations\Contracts\AccessControlProviderInterface::class,
            \App\Domain\Integrations\Adapters\SimulatorAccessControlProvider::class
        );

        $this->app->bind(
            \App\Domain\Integrations\Contracts\CardReaderInterface::class,
            \App\Domain\Integrations\Adapters\KeyboardCardReader::class
        );

        $this->app->bind(
            \App\Domain\Integrations\Contracts\ReceiptPrinterInterface::class,
            \App\Domain\Integrations\Adapters\ThermalReceiptPrinter::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User) {
                app(AuditLogService::class)->logLogin($event->user, $event->guard ?? 'web');
            }
        });

        Event::listen(Failed::class, function (Failed $event): void {
            $email = $event->credentials['email'] ?? null;
            app(AuditLogService::class)->logFailedLogin($email);
        });

        Event::listen(Logout::class, function (Logout $event): void {
            if ($event->user instanceof User) {
                app(AuditLogService::class)->logLogout($event->user);
            }
        });
    }
}
