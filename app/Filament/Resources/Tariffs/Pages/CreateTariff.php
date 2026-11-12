<?php

namespace App\Filament\Resources\Tariffs\Pages;

use App\Domain\Inventory\Services\InventoryService;
use App\Domain\Shared\Exceptions\UnauthorizedActionException;
use App\Filament\Resources\Tariffs\TariffResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class CreateTariff extends CreateRecord
{
    protected static string $resource = TariffResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $trackInventory = (bool) ($data['track_inventory'] ?? false);
        $mappingData = $trackInventory ? [
            'inventory_item_id' => $data['serving_inventory_item_id'] ?? null,
            'serving_name' => $data['serving_name'] ?? null,
            'serving_qty' => $data['serving_qty'] ?? null,
        ] : null;

        try {
            return app(InventoryService::class)->createTariffWithServingMapping(
                tariffData: $data,
                mappingData: $mappingData,
                actor: auth()->user(),
            );
        } catch (InvalidArgumentException|UnauthorizedActionException $e) {
            Notification::make()
                ->title('Unable to Save Menu Item')
                ->body($e->getMessage())
                ->danger()
                ->send();

            $this->halt();
            throw $e;
        }
    }
}
