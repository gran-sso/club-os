<?php

namespace App\Filament\Resources\BankAccounts\Pages;

use App\Domain\Finance\Models\BankAccount;
use App\Filament\Resources\BankAccounts\BankAccountResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;

class EditBankAccount extends EditRecord
{
    protected static string $resource = BankAccountResource::class;

    protected Width | string | null $maxContentWidth = Width::SevenExtraLarge;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (!empty($data['is_primary'])) {
            BankAccount::where('id', '!=', $record->id)
                ->where('is_primary', true)
                ->update(['is_primary' => false]);
        }

        return parent::handleRecordUpdate($record, $data);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

