<?php

namespace App\Filament\Resources\Promotions\Pages;

use App\Filament\Resources\Promotions\PromotionResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePromotion extends CreateRecord
{
    protected static string $resource = PromotionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = $data['type'] ?? \App\Domain\Billing\Models\Promotion::TYPE_BUY_X_GET_Y;
        $data['created_by_user_id'] = auth()->id();

        return $data;
    }
}

