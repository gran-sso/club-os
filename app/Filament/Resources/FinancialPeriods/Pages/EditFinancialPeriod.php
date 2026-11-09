<?php

namespace App\Filament\Resources\FinancialPeriods\Pages;

use App\Filament\Resources\FinancialPeriods\FinancialPeriodResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditFinancialPeriod extends EditRecord
{
    protected static string $resource = FinancialPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $newStatus = $data['status'] ?? $record->status;
        $closedAt = $record->closed_at;
        $closedBy = $record->closed_by_user_id;

        if ($newStatus === 'closed' && $record->status !== 'closed') {
            $closedAt = now();
            $closedBy = auth()->id();
        } elseif ($newStatus !== 'closed') {
            $closedAt = null;
            $closedBy = null;
        }

        $record->update([
            'period_code' => $data['period_code'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'status' => $newStatus,
            'closed_at' => $closedAt,
            'closed_by_user_id' => $closedBy,
            'notes' => $data['notes'] ?? null,
        ]);

        return $record;
    }
}

