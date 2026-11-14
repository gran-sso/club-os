<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Vite;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(\App\Http\Controllers\AdminLoginRedirectController::class)
            ->brandName('Malabar Club')
            ->brandLogo(fn () => view('filament.components.brand-logo'))
            ->brandLogoHeight('2.5rem')
            ->font('Outfit')
            ->colors([
                'primary' => Color::Amber,
                'gray' => Color::Slate,
            ])
            ->darkMode(false)
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth('full')
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): Htmlable => new \Illuminate\Support\HtmlString(
                    view('filament.components.pwa-head')->render() .
                    app(Vite::class)(['resources/css/app.css'])->toHtml()
                ),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): Htmlable => new \Illuminate\Support\HtmlString(
                    view('filament.components.pwa-mobile-nav')->render()
                ),
            )
            ->navigationGroups([
                'Operations & Front Desk',
                'Membership & Guest Desk',
                'Commercial & Billing',
                'Procurement & Food Contractor',
                'Finance & Audit Ledger',
                'Club Master Setup',
                'System & Access Control',
            ])
            ->navigationItems([
                NavigationItem::make('Counter POS Terminal')
                    ->url(fn (): string => route('pos'))
                    ->icon(Heroicon::OutlinedBolt)
                    ->sort(-1)
                    ->visible(fn (): bool => (bool) ($user = auth()->user())
                        && ! $user->isSuperAdmin()
                        && $user->hasClubPermission('counter.operate')),
            ])
            ->userMenuItems([
                \Filament\Navigation\MenuItem::make()
                    ->label('Change My Password')
                    ->url(fn (): string => route('password.change'))
                    ->icon(Heroicon::OutlinedKey),
                \Filament\Navigation\MenuItem::make()
                    ->label('Help')
                    ->url(fn (): string => url('/admin/help'))
                    ->icon(Heroicon::OutlinedQuestionMarkCircle),
            ])
            ->globalSearch(\App\Domain\Shared\Services\ClubGlobalSearchProvider::class)
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->globalSearchFieldKeyBindingSuffix()
            ->globalSearchDebounce('350ms')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                \App\Filament\Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                \App\Filament\Widgets\ExecutiveStatsOverviewWidget::class,
                \App\Filament\Widgets\FrontDeskPulseWidget::class,
                \App\Filament\Widgets\CounterStationsPulseWidget::class,
                \App\Filament\Widgets\DepartmentRevenueChart::class,
                \App\Filament\Widgets\ContractorAndShiftStatusWidget::class,
                \App\Filament\Widgets\SystemIntegrityWidget::class,
                \App\Filament\Widgets\RecentActivityTableWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                \App\Http\Middleware\EnsurePasswordIsChanged::class,
            ]);
    }
}
