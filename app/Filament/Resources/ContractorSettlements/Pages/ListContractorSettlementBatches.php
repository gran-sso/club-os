<?php

namespace App\Filament\Resources\ContractorSettlements\Pages;

use App\Domain\Contractor\Models\Contractor;
use App\Domain\Contractor\Services\ContractorSettlementService;
use App\Filament\Resources\ContractorSettlements\ContractorSettlementBatchResource;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListContractorSettlementBatches extends ListRecords
{
    protected static string $resource = ContractorSettlementBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateBatch')
                ->label('Generate Daily Settlement Batch')
                ->icon(Heroicon::OutlinedSparkles)
                ->color('primary')
                ->visible(fn (): bool => (bool) auth()->user()?->hasClubPermission('contractor.settle'))
                ->form([
                    DatePicker::make('business_date')
                        ->label('Cutoff Business Date')
                        ->default(now()->subDay()->toDateString())
                        ->required()
                        ->helperText('Food sales up to the cutoff time on this date will be batched for settlement.'),
                ])
                ->action(function (array $data) {
                    abort_unless((bool) auth()->user()?->hasClubPermission('contractor.settle'), 403);
                    $contractor = Contractor::first();
                    if (!$contractor) {
                        Notification::make()
                            ->title('No Catering Contractor Configured')
                            ->danger()
                            ->send();
                        return;
                    }

                    $service = app(ContractorSettlementService::class);
                    $batch = $service->createSettlementBatch($contractor, $data['business_date'], auth()->id());

                    Notification::make()
                        ->title("Settlement Batch {$batch->batch_number} Generated")
                        ->body("Net payable: ₹" . number_format($batch->net_payable_paise / 100, 2) . " across " . $batch->allocations()->count() . " food bill lines.")
                        ->success()
                        ->send();
                }),
        ];
    }
}

