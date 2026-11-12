<?php

namespace App\Filament\Widgets;

use App\Domain\Billing\Models\PosHeldTab;
use App\Domain\Billing\Models\Shift;
use App\Domain\Billing\Services\ShiftService;
use App\Domain\Shared\Exceptions\ShiftNotOpenException;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use InvalidArgumentException;

class CounterStationsPulseWidget extends Widget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'filament.widgets.counter-stations-pulse';

    protected ?string $pollingInterval = '30s';

    public bool $showCloseModal = false;

    public ?int $closingShiftId = null;

    public string $closingShiftNumber = '';

    public string $closingCounterName = '';

    public int $closingExpectedCashPaise = 0;

    public string $countedCash = '';

    public string $closeNotes = '';

    public static function canView(): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->hasAnyClubPermission(['shifts.view', 'shifts.manage']));
    }

    public function openCloseShiftModal(int $shiftId): void
    {
        abort_unless((bool) auth()->user()?->hasClubPermission('shifts.manage'), 403);

        $shift = Shift::with('counter')->findOrFail($shiftId);

        if (! $shift->isOpen()) {
            Notification::make()
                ->title('Shift Already Closed')
                ->body("Shift #{$shift->shift_number} has already been closed.")
                ->warning()
                ->send();

            return;
        }

        $openTabsCount = PosHeldTab::forShift($shift->id)->open()->count();
        if ($openTabsCount > 0) {
            Notification::make()
                ->title('Cannot Close Shift With Open Bar Tabs')
                ->body("Shift #{$shift->shift_number} still has {$openTabsCount} unsettled running bar tab(s). Settle or void all open tabs before closing.")
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        $expectedCashPaise = $shift->calculateExpectedCashPaise();

        $this->resetValidation();
        $this->closingShiftId = $shift->id;
        $this->closingShiftNumber = $shift->shift_number;
        $this->closingCounterName = $shift->counter?->name ?? 'Billing Counter';
        $this->closingExpectedCashPaise = $expectedCashPaise;
        $this->countedCash = number_format($expectedCashPaise / 100, 2, '.', '');
        $this->closeNotes = '';
        $this->showCloseModal = true;
    }

    public function cancelCloseShiftModal(): void
    {
        $this->reset([
            'showCloseModal',
            'closingShiftId',
            'closingShiftNumber',
            'closingCounterName',
            'closingExpectedCashPaise',
            'countedCash',
            'closeNotes',
        ]);
        $this->resetValidation();
    }

    public function confirmCloseShift(): void
    {
        abort_unless((bool) auth()->user()?->hasClubPermission('shifts.manage'), 403);

        $validated = $this->validate([
            'closingShiftId' => ['required', 'integer'],
            'countedCash' => ['required', 'numeric', 'min:0'],
            'closeNotes' => ['nullable', 'string', 'max:500'],
        ]);

        $shift = Shift::findOrFail((int) $validated['closingShiftId']);
        $countedPaise = (int) round(((float) $validated['countedCash']) * 100);

        try {
            $closed = app(ShiftService::class)->closeShift(
                shift: $shift,
                closingCashCountedPaise: $countedPaise,
                notes: filled($validated['closeNotes'] ?? null) ? trim((string) $validated['closeNotes']) : null
            );
        } catch (InvalidArgumentException | ShiftNotOpenException $e) {
            Notification::make()
                ->title('Cannot Close Shift')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();

            $this->cancelCloseShiftModal();

            return;
        }

        $this->cancelCloseShiftModal();

        Notification::make()
            ->title("Shift #{$closed->shift_number} Closed")
            ->body(
                'Drawer reconciled. Counted: ₹'
                . number_format(($closed->closing_cash_counted_paise ?? 0) / 100, 2)
                . ' (Expected: ₹'
                . number_format(($closed->expected_cash_paise ?? 0) / 100, 2)
                . ').'
            )
            ->success()
            ->send();
    }

    public function getViewData(): array
    {
        $snapshots = app(ShiftService::class)->getCounterStationSnapshots();

        return [
            'stations' => $snapshots,
            'totalCountersCount' => $snapshots->count(),
            'openCountersCount' => $snapshots->where('is_open', true)->count(),
            'staleShiftsCount' => $snapshots->where('is_stale', true)->count(),
            'totalExpectedCashPaise' => (int) $snapshots->sum('expected_cash_paise'),
            'canManageShifts' => (bool) auth()->user()?->hasClubPermission('shifts.manage'),
        ];
    }
}

