<?php

namespace App\Filament\Resources\Accounts\Pages;

use App\Domain\Finance\Models\Account;
use App\Filament\Resources\Accounts\AccountResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditAccount extends EditRecord
{
    protected static string $resource = AccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => ! $this->getRecord()->is_system)
                ->before(function (Account $record, DeleteAction $action) {
                    if ($record->is_system) {
                        Notification::make()
                            ->title('Deletion Prohibited')
                            ->body('System accounts cannot be deleted as automated double-entry postings depend on them.')
                            ->danger()
                            ->send();

                        $action->cancel();

                        return;
                    }

                    if ($record->hasHistoricalLines()) {
                        Notification::make()
                            ->title('Deletion Prohibited (Audit Trail)')
                            ->body('This ledger head has linked historical journal entries or expense vouchers. Deactivate it instead.')
                            ->warning()
                            ->send();

                        $action->cancel();

                        return;
                    }
                }),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

