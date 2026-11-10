<?php

namespace App\Filament\Resources\MemberNotices\Pages;

use App\Domain\Communication\Services\NoticeService;
use App\Domain\Membership\Models\Member;
use App\Filament\Resources\MemberNotices\MemberNoticeResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListMemberNotices extends ListRecords
{
    protected static string $resource = MemberNoticeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('broadcastDuesReminder')
                ->label('Broadcast Dues Reminder')
                ->icon('heroicon-o-bell-alert')
                ->color('warning')
                ->modalHeading('Broadcast Annual Dues Reminder')
                ->modalDescription(function () {
                    $pendingCount = Member::where('fee_status', 'pending')->count();
                    return "This will instantly create a high-priority banner notice and bulletin for all {$pendingCount} member(s) who currently have pending annual subscription or admission fees.";
                })
                ->form([
                    Textarea::make('custom_notes')
                        ->label('Custom Note from Accounts (Optional)')
                        ->placeholder('e.g. Please note that payments before 31st March avoid statutory late fees.')
                        ->rows(3),

                    Toggle::make('send_whatsapp')
                        ->label('Queue for WhatsApp Outbox')
                        ->default(false),

                    Toggle::make('send_sms')
                        ->label('Queue for SMS Outbox')
                        ->default(false),
                ])
                ->action(function (array $data, NoticeService $noticeService) {
                    $pendingCount = Member::where('fee_status', 'pending')->count();

                    if ($pendingCount === 0) {
                        Notification::make()
                            ->title('No Members with Pending Dues')
                            ->body('All active club members have settled their dues.')
                            ->info()
                            ->send();
                        return;
                    }

                    $notice = $noticeService->broadcastDuesReminders(
                        userId: auth()->id(),
                        customNotes: $data['custom_notes'] ?? null,
                        sendWhatsApp: (bool) ($data['send_whatsapp'] ?? false),
                        sendSms: (bool) ($data['send_sms'] ?? false)
                    );

                    Notification::make()
                        ->title('Dues Reminder Notice Broadcasted')
                        ->body("Published notice '{$notice->title}' to {$pendingCount} member(s) with pending dues.")
                        ->success()
                        ->send();
                }),

            CreateAction::make()
                ->label('New Member Notice'),
        ];
    }
}
