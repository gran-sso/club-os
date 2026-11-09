<?php

namespace App\Providers;

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
        //
    }
}
