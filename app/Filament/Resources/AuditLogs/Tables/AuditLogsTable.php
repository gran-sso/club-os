<?php

namespace App\Filament\Resources\AuditLogs\Tables;

use App\Domain\Shared\Models\AuditLog;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date & Time')
                    ->dateTime('d M Y, h:i:s A')
                    ->sortable()
                    ->description(fn (AuditLog $record): ?string => $record->created_at?->diffForHumans()),

                TextColumn::make('user_name')
                    ->label('Staff Member')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (AuditLog $record): ?string => $record->user_role ? strtoupper($record->user_role) : null),

                TextColumn::make('category')
                    ->label('Category')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'security', 'data_privacy' => 'danger',
                        'governance' => 'primary',
                        'tariffs', 'inventory' => 'warning',
                        'shifts' => 'danger',
                        'members' => 'info',
                        'finance' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('action')
                    ->label('Action')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->fontFamily(FontFamily::Mono),

                TextColumn::make('description')
                    ->label('Activity Summary')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('ip_address')
                    ->label('IP / Terminal')
                    ->fontFamily(FontFamily::Mono)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label('Action Category')
                    ->options([
                        'security' => 'Security & Authentication',
                        'governance' => 'Roles & Club Settings',
                        'tariffs' => 'Menu & Pricing Rules',
                        'inventory' => 'Bar & Store Inventory',
                        'members' => 'Members & Admissions',
                        'shifts' => 'Counter Shifts & Variances',
                        'facilities' => 'Rooms & Badminton Courts',
                        'data_privacy' => 'Data Exports & Confidential KYC',
                        'finance' => 'Finance & Accounting',
                    ]),

                SelectFilter::make('user_id')
                    ->label('Staff User')
                    ->options(fn (): array => User::orderBy('name')->pluck('name', 'id')->all()),

                Filter::make('today')
                    ->label("Today's Actions Only")
                    ->query(fn (Builder $query): Builder => $query->whereDate('created_at', now()->toDateString())),

                Filter::make('last_7_days')
                    ->label('Last 7 Days')
                    ->query(fn (Builder $query): Builder => $query->where('created_at', '>=', now()->subDays(7))),

                Filter::make('security_or_variances')
                    ->label('Security & Shift Discrepancies')
                    ->query(fn (Builder $query): Builder => $query->whereIn('category', ['security', 'shifts', 'data_privacy'])),
            ])
            ->recordActions([
                Action::make('viewDetails')
                    ->label('Details')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn (AuditLog $record): string => "Audit Log Entry #{$record->id} — {$record->action}")
                    ->modalContent(fn (AuditLog $record) => view('filament.components.audit-log-detail-modal', ['log' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->toolbarActions([]);
    }
}

