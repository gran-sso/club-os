<?php

namespace App\Filament\Resources\MemberNotices\Schemas;

use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MembershipCategory;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MemberNoticeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Notice Details & Classification')
                    ->description('Specify the subject, issuing department, priority, and prominent display options for the member portal.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('title')
                                ->label('Notice Title / Headline')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('e.g. Annual Subscription Dues Reminder or Badminton Wooden Court AMC Maintenance')
                                ->columnSpan(2),

                            Select::make('department')
                                ->label('Issuing Department')
                                ->options([
                                    'admin' => '🏛️ Administration & Secretary',
                                    'accounts' => '💰 Accounts & Finance',
                                    'reception' => '🏨 Reception & Front Desk',
                                    'sports' => '🏸 Sports & Fitness',
                                    'food' => '🍽️ Dining & Food Contractor',
                                ])
                                ->default('admin')
                                ->required(),
                        ]),

                        Grid::make(3)->schema([
                            Select::make('priority')
                                ->label('Priority / Alert Level')
                                ->options([
                                    'info' => '🔵 General Information',
                                    'reminder' => '🟡 Payment / Renewal Reminder',
                                    'urgent' => '🔴 Urgent Alert / Maintenance',
                                    'event' => '🟢 Event / Celebration',
                                ])
                                ->default('info')
                                ->required(),

                            Toggle::make('is_banner')
                                ->label('Display Prominent Dashboard Banner')
                                ->helperText('If enabled, renders as a sticky alert banner at the top of the member PWA dashboard until dismissed.')
                                ->default(false),

                            Toggle::make('is_active')
                                ->label('Active / Published')
                                ->helperText('Visible to targeted members in the portal.')
                                ->default(true),
                        ]),

                        Textarea::make('content')
                            ->label('Notice Content / Message Body')
                            ->required()
                            ->rows(5)
                            ->placeholder('Enter the detailed message for members...')
                            ->columnSpanFull(),
                    ]),

                Section::make('Target Audience')
                    ->description('Control which club members will receive and view this notification in their PWA portal.')
                    ->columnSpanFull()
                    ->schema([
                        Select::make('target_type')
                            ->label('Audience Scope')
                            ->options([
                                'all' => '📢 All Active Members (Club-wide Broadcast)',
                                'dues_pending' => '⚠️ Members with Pending Annual / Admission Dues',
                                'category' => '🏷️ Specific Membership Category',
                                'individual' => '👤 Specific Individual Member',
                            ])
                            ->default('all')
                            ->required()
                            ->reactive(),

                        Grid::make(2)->schema([
                            Select::make('target_category_id')
                                ->label('Target Membership Category')
                                ->options(fn () => MembershipCategory::pluck('name', 'id'))
                                ->visible(fn ($get) => $get('target_type') === 'category')
                                ->required(fn ($get) => $get('target_type') === 'category')
                                ->searchable(),

                            Select::make('target_member_id')
                                ->label('Target Specific Member')
                                ->options(fn () => Member::with('person')->get()->mapWithKeys(fn ($m) => [
                                    $m->id => "{$m->name} ({$m->membership_number})",
                                ]))
                                ->visible(fn ($get) => $get('target_type') === 'individual')
                                ->required(fn ($get) => $get('target_type') === 'individual')
                                ->searchable(),
                        ]),
                    ]),

                Section::make('Call-to-Action & Schedule')
                    ->description('Optionally add an interactive button link and schedule validity window.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('action_label')
                                ->label('Action Button Label (Optional)')
                                ->placeholder('e.g. Review Dues & Pay, or View Court Schedule')
                                ->maxLength(60),

                            TextInput::make('action_url')
                                ->label('Action Button Link (Optional)')
                                ->placeholder('e.g. /member/dashboard?tab=wallet or https://wa.me/91...')
                                ->maxLength(255),
                        ]),

                        Grid::make(2)->schema([
                            DateTimePicker::make('published_at')
                                ->label('Publish At')
                                ->default(now())
                                ->required()
                                ->helperText('Scheduled date/time when this notice becomes visible to members.'),

                            DateTimePicker::make('expires_at')
                                ->label('Expires At (Optional)')
                                ->helperText('Notice automatically archives after this timestamp (e.g. after event or deadline).'),
                        ]),
                    ]),

                Section::make('External Outbox Channels (Phase 2 Preparation)')
                    ->description('Queue outbound messages to transactional outbox for external dispatch.')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            Toggle::make('send_whatsapp')
                                ->label('Queue WhatsApp Notification')
                                ->helperText('Enqueues outbound WhatsApp notification for targeted members into the integrations outbox.')
                                ->default(false),

                            Toggle::make('send_sms')
                                ->label('Queue SMS Notification')
                                ->helperText('Enqueues outbound SMS notification for targeted members into the integrations outbox.')
                                ->default(false),
                        ]),
                    ]),
            ]);
    }
}

