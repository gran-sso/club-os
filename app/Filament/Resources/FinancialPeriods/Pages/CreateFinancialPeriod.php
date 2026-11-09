<?php

namespace App\Filament\Resources\FinancialPeriods\Pages;

use App\Domain\Finance\Models\FinancialPeriod;
use App\Filament\Resources\FinancialPeriods\FinancialPeriodResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateFinancialPeriod extends CreateRecord
{
    protected static string $resource = FinancialPeriodResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $closedAt = ($data['status'] ?? 'open') === 'closed' ? now() : null;
        $closedBy = ($data['status'] ?? 'open') === 'closed' ? auth()->id() : null;

        return FinancialPeriod::create([
            'period_code' => $data['period_code'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'status' => $data['status'] ?? 'open',
            'closed_at' => $closedAt,
            'closed_by_user_id' => $closedBy,
            'notes' => $data['notes'] ?? null,
        ]);
    }
}

