<?php

namespace App\Filament\Resources\BankAccounts\Pages;

use App\Domain\Finance\Models\BankAccount;
use App\Filament\Resources\BankAccounts\BankAccountResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;

class CreateBankAccount extends CreateRecord
{
    protected static string $resource = BankAccountResource::class;

    protected Width | string | null $maxContentWidth = Width::SevenExtraLarge;

    protected function handleRecordCreation(array $data): Model
    {
        if (!empty($data['is_primary'])) {
            BankAccount::where('is_primary', true)->update(['is_primary' => false]);
        }

        $opening = (int) ($data['opening_balance_paise'] ?? 0);
        $data['current_balance_paise'] = $opening;

        return parent::handleRecordCreation($data);
    }
}

