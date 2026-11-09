<?php

namespace App\Filament\Resources\Contractors\Pages;

use App\Domain\Contractor\Models\Contractor;
use App\Filament\Resources\Contractors\ContractorResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditContractor extends EditRecord
{
    protected static string $resource = ContractorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->before(function (Contractor $record, DeleteAction $action) {
                    if ($record->settlementBatches()->exists()) {
                        Notification::make()
                            ->title('Deletion Prohibited: Existing Settlement Batches')
                            ->body("Cannot delete '{$record->name}' because {$record->settlementBatches()->count()} settlement batch(es) have been recorded for this contractor. Deactivate the contractor instead to preserve financial audit history.")
                            ->danger()
                            ->send();

                        $action->halt();
                    }
                }),
        ];
    }
}

