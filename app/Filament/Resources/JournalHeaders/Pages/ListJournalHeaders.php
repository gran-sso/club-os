<?php

namespace App\Filament\Resources\JournalHeaders\Pages;

use App\Domain\Integrations\TallySyncService;
use App\Domain\Shared\Models\SystemSetting;
use App\Filament\Resources\JournalHeaders\JournalHeaderResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListJournalHeaders extends ListRecords
{
    protected static string $resource = JournalHeaderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadTallyMastersXml')
                ->label('Export Tally Masters XML')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->visible(fn (): bool => (bool) (auth()->user()?->hasClubPermission('journals.view') || auth()->user()?->hasClubPermission('integrations.view')))
                ->action(function (TallySyncService $syncService): StreamedResponse {
                    $user = auth()->user();
                    abort_unless((bool) ($user && ($user->hasClubPermission('journals.view') || $user->hasClubPermission('integrations.view'))), 403);

                    $export = $syncService->buildMastersXmlExport();

                    return response()->streamDownload(function () use ($export): void {
                        echo $export['xml'];
                    }, $export['filename'], [
                        'Content-Type' => 'application/xml; charset=UTF-8',
                    ]);
                }),

            Action::make('downloadTallyVouchersXml')
                ->label('Export Tally Vouchers XML')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->visible(fn (): bool => (bool) (auth()->user()?->hasClubPermission('journals.view') || auth()->user()?->hasClubPermission('integrations.view')))
                ->modalHeading('Export Journals to TallyPrime XML')
                ->modalDescription('Download posted club journals in TallyPrime-compatible XML format for manual import via Gateway of Tally -> Import Data -> Vouchers.')
                ->modalSubmitActionLabel('Download Vouchers XML')
                ->form([
                    Radio::make('scope')
                        ->label('Export Scope')
                        ->options([
                            'pending_only' => 'Pending (Unsynced) Posted Journals Only',
                            'date_range' => 'All Posted Journals in Date Range',
                        ])
                        ->default('pending_only')
                        ->live()
                        ->required(),

                    DatePicker::make('from_date')
                        ->label('From Date')
                        ->default(fn () => now()->startOfMonth()->toDateString())
                        ->visible(fn (Get $get): bool => $get('scope') === 'date_range')
                        ->required(fn (Get $get): bool => $get('scope') === 'date_range'),

                    DatePicker::make('to_date')
                        ->label('To Date')
                        ->default(fn () => now()->toDateString())
                        ->visible(fn (Get $get): bool => $get('scope') === 'date_range')
                        ->required(fn (Get $get): bool => $get('scope') === 'date_range'),

                    Toggle::make('include_cost_centres')
                        ->label('Include Department Cost Centres (P&L Ledgers Only)')
                        ->helperText('Enable only if Cost Centres are active in your TallyPrime company features (F11).')
                        ->default(fn (): bool => SystemSetting::getBool('tally_include_cost_centres', false)),

                    Toggle::make('mark_as_synced')
                        ->label('Mark exported journals as synced in Outbox')
                        ->helperText('Marks pending outbox entries for these journals as acknowledged so they are not re-exported as pending.')
                        ->default(false)
                        ->visible(fn (): bool => (bool) (auth()->user()?->hasClubPermission('journals.manage') || auth()->user()?->hasClubPermission('integrations.manage'))),
                ])
                ->action(function (array $data, TallySyncService $syncService): ?StreamedResponse {
                    $user = auth()->user();
                    abort_unless((bool) ($user && ($user->hasClubPermission('journals.view') || $user->hasClubPermission('integrations.view'))), 403);

                    $markAsSynced = (bool) ($data['mark_as_synced'] ?? false);
                    if ($markAsSynced) {
                        abort_unless((bool) ($user->hasClubPermission('journals.manage') || $user->hasClubPermission('integrations.manage')), 403);
                    }

                    $pendingOnly = ($data['scope'] ?? 'pending_only') === 'pending_only';
                    $fromDate = $pendingOnly ? null : ($data['from_date'] ?? null);
                    $toDate = $pendingOnly ? null : ($data['to_date'] ?? null);

                    if (! $pendingOnly && $fromDate && $toDate && $fromDate > $toDate) {
                        Notification::make()
                            ->title('Invalid Date Range')
                            ->body('From Date cannot be later than To Date.')
                            ->danger()
                            ->send();

                        return null;
                    }

                    $includeCostCentres = (bool) ($data['include_cost_centres'] ?? false);

                    $export = $syncService->buildVouchersXmlExport(
                        fromDate: $fromDate,
                        toDate: $toDate,
                        pendingOnly: $pendingOnly,
                        includeCostCentres: $includeCostCentres
                    );

                    if ($export['count'] === 0) {
                        Notification::make()
                            ->title('No Posted Journals to Export')
                            ->body($pendingOnly
                                ? 'There are currently no pending posted journals waiting for Tally sync.'
                                : 'No posted journals were found in the selected date range.')
                            ->warning()
                            ->send();

                        return null;
                    }

                    if ($markAsSynced) {
                        $syncService->markExportedAsAcknowledged($export['journal_ids']);
                    }

                    return response()->streamDownload(function () use ($export): void {
                        echo $export['xml'];
                    }, $export['filename'], [
                        'Content-Type' => 'application/xml; charset=UTF-8',
                    ]);
                }),

            CreateAction::make(),
        ];
    }
}
