<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => auth()->id() !== $this->getRecord()->id)
                ->before(function (User $record, DeleteAction $action) {
                    if ($record->hasRole('admin') && User::role('admin')->count() <= 1) {
                        Notification::make()
                            ->title('Deletion Blocked')
                            ->body('You cannot delete the sole remaining administrator.')
                            ->danger()
                            ->send();

                        $action->cancel();

                        return;
                    }

                    if ($record->hasHistoricalRecords()) {
                        Notification::make()
                            ->title('Deletion Blocked (Audit Integrity)')
                            ->body("{$record->name} has linked operational records (shifts, bills, purchase receipts, or journal vouchers). To maintain legal audit integrity, deactivate this account instead of deleting it.")
                            ->warning()
                            ->send();

                        $action->cancel();

                        return;
                    }
                }),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->getRecord();

        // Prevent self-deactivation
        if (auth()->id() === $record->id && isset($data['is_active']) && ! $data['is_active']) {
            throw ValidationException::withMessages([
                'is_active' => 'You cannot deactivate your own currently active administrator account.',
            ]);
        }

        // Prevent deactivating sole admin
        if ($record->hasRole('admin') && isset($data['is_active']) && ! $data['is_active']) {
            $otherActiveAdmins = User::role('admin')
                ->where('is_active', true)
                ->where('id', '!=', $record->id)
                ->count();

            if ($otherActiveAdmins === 0) {
                throw ValidationException::withMessages([
                    'is_active' => 'Cannot deactivate this account because it is the sole remaining active administrator.',
                ]);
            }
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

