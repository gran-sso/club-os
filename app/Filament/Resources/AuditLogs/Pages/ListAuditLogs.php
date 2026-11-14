<?php

namespace App\Filament\Resources\AuditLogs\Pages;

use App\Domain\Shared\Models\SystemSetting;
use App\Domain\Shared\Services\AuditLogService;
use App\Filament\Resources\AuditLogs\AuditLogResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListAuditLogs extends ListRecords
{
    protected static string $resource = AuditLogResource::class;

    protected function getHeaderActions(): array
    {
        $retentionDays = (int) SystemSetting::get('audit_log_retention_days', 30);

        return [
            Action::make('retentionInfo')
                ->label("Retention: {$retentionDays} Days")
                ->icon('heroicon-o-clock')
                ->color('gray')
                ->tooltip('Configure retention period under System & Access Control -> System Settings -> Audit Trail')
                ->url(fn (): string => auth()->user()?->hasClubPermission('settings.manage') ? '/admin/settings' : '#')
                ->openUrlInNewTab(false),

            Action::make('pruneNow')
                ->label('Prune Expired')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Prune Expired Audit Logs')
                ->modalDescription("Permanently delete all audit log records older than {$retentionDays} days based on the club's configured retention policy?")
                ->visible(fn (): bool => (bool) auth()->user()?->hasClubPermission('settings.manage'))
                ->action(function (): void {
                    $deleted = app(AuditLogService::class)->pruneExpired();
                    Notification::make()
                        ->title('Audit Logs Pruned')
                        ->body("Deleted {$deleted} expired audit log entries.")
                        ->success()
                        ->send();
                }),
        ];
    }
}

