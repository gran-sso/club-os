<?php

namespace App\Filament\Resources\Promotions\Pages;

use App\Domain\Billing\Models\BillLine;
use App\Filament\Resources\Promotions\PromotionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPromotion extends EditRecord
{
    protected static string $resource = PromotionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn () => (int) $this->record->redeemed_free_units === 0
                    && !BillLine::where('promotion_id', $this->record->id)->exists()),
        ];
    }
}

