<?php

namespace App\Filament\Resources\Tariffs\Pages;

use App\Domain\Inventory\Services\InventoryService;
use App\Domain\Shared\Exceptions\UnauthorizedActionException;
use App\Filament\Resources\Tariffs\TariffResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class EditTariff extends EditRecord
{
    protected static string $resource = TariffResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();
        $record->loadMissing('servingMapping');
        $mapping = $record->servingMapping;

        if ($mapping) {
            $data['track_inventory'] = true;
            $data['serving_inventory_item_id'] = $mapping->inventory_item_id;
            $data['serving_name'] = $mapping->serving_name;
            $data['serving_qty'] = (float) $mapping->serving_qty;
        } else {
            $data['track_inventory'] = false;
        }

        $data['serving_preset'] = 'custom';

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $trackInventory = (bool) ($data['track_inventory'] ?? false);
        $inventoryItemId = $trackInventory && ! empty($data['serving_inventory_item_id'])
            ? (int) $data['serving_inventory_item_id']
            : null;
        $servingName = $data['serving_name'] ?? null;
        $servingQty = isset($data['serving_qty']) ? (float) $data['serving_qty'] : null;

        $tariffData = Arr::only($data, [
            'code',
            'barcode',
            'department_code',
            'name',
            'rate_paise',
            'tax_percent',
            'effective_from',
            'effective_to',
            'is_active',
        ]);

        try {
            return DB::transaction(function () use ($record, $tariffData, $inventoryItemId, $servingName, $servingQty): Model {
                $record->update($tariffData);

                app(InventoryService::class)->syncTariffServingMapping(
                    tariff: $record,
                    inventoryItemId: $inventoryItemId,
                    servingName: $servingName,
                    servingQty: $servingQty,
                    actor: auth()->user(),
                );

                return $record->fresh(['servingMapping.inventoryItem']);
            });
        } catch (InvalidArgumentException|UnauthorizedActionException $e) {
            Notification::make()
                ->title('Unable to Update Menu Item')
                ->body($e->getMessage())
                ->danger()
                ->send();

            $this->halt();
            throw $e;
        }
    }
}
