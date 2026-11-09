<?php

namespace App\Filament\Resources\Expenses\Pages;

use App\Domain\Finance\Services\ExpenseService;
use App\Filament\Resources\Expenses\ExpenseResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;

class CreateExpense extends CreateRecord
{
    protected static string $resource = ExpenseResource::class;

    protected Width | string | null $maxContentWidth = Width::SevenExtraLarge;

    protected function handleRecordCreation(array $data): Model
    {
        return app(ExpenseService::class)->recordExpense(
            $data,
            auth()->user()
        );
    }
}

