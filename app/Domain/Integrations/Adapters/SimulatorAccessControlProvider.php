<?php

namespace App\Domain\Integrations\Adapters;

use App\Domain\Access\Models\AccessEvent;
use App\Domain\Access\Models\GuestPass;
use App\Domain\Integrations\Contracts\AccessControlProviderInterface;
use App\Domain\Membership\Models\Card;
use App\Domain\Membership\Models\Member;
use App\Domain\Shared\Enums\CardStatus;
use App\Domain\Shared\Enums\PassStatus;
use RuntimeException;

class SimulatorAccessControlProvider implements AccessControlProviderInterface
{
    public const STATUS_LABEL = 'SIMULATOR - TEST ONLY';

    public function authorizeEntry(string $tokenOrCode, string $facilityCode): array
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Hardware access simulator cannot be executed in production environment.');
        }

        $token = trim($tokenOrCode);

        // Check 1: Is this a physical card?
        $card = Card::where('card_number', $token)->first();
        if ($card) {
            if ($card->status !== CardStatus::ACTIVE) {
                $this->logEvent(null, null, $token, $facilityCode, 'denied', "Card is {$card->status->value}");
                return [
                    'granted' => false,
                    'reason' => "Card is {$card->status->value}",
                    'is_simulator' => true,
                ];
            }

            if ($card->cardable instanceof Member) {
                $member = $card->cardable;
                if (!$member->isActive()) {
                    $this->logEvent($member->person_id, null, $token, $facilityCode, 'denied', 'Membership inactive or expired');
                    return [
                        'granted' => false,
                        'reason' => 'Membership is inactive or expired.',
                        'is_simulator' => true,
                    ];
                }

                $this->logEvent($member->person_id, null, $token, $facilityCode, 'granted', 'Valid member access');
                return [
                    'granted' => true,
                    'holder_type' => 'member',
                    'holder_name' => $member->person->full_name,
                    'membership_number' => $member->membership_number,
                    'is_simulator' => true,
                ];
            }
        }

        // Check 2: Is this a Guest Pass code?
        $pass = GuestPass::where('pass_code', $token)->first();
        if ($pass) {
            if ($pass->facility_code !== $facilityCode && $pass->facility_code !== 'all') {
                $this->logEvent($pass->guest->person_id, $pass->id, $token, $facilityCode, 'denied', 'Pass not valid for this facility');
                return [
                    'granted' => false,
                    'reason' => 'Guest pass is not valid for facility: ' . $facilityCode,
                    'is_simulator' => true,
                ];
            }

            if (!$pass->isValidForEntry()) {
                $this->logEvent($pass->guest->person_id, $pass->id, $token, $facilityCode, 'denied', "Pass is {$pass->status->value} or expired");
                return [
                    'granted' => false,
                    'reason' => "Pass is {$pass->status->value} or expired.",
                    'is_simulator' => true,
                ];
            }

            // Increment entry count
            $pass->increment('entry_count');
            if ($pass->entry_count >= $pass->max_entries) {
                $pass->update(['status' => PassStatus::USED]);
            }

            $this->logEvent($pass->guest->person_id, $pass->id, $token, $facilityCode, 'granted', 'Valid guest pass');
            return [
                'granted' => true,
                'holder_type' => 'guest',
                'holder_name' => $pass->guest->person->full_name,
                'pass_code' => $pass->pass_code,
                'is_simulator' => true,
            ];
        }

        $this->logEvent(null, null, $token, $facilityCode, 'denied', 'Unrecognized card or pass');
        return [
            'granted' => false,
            'reason' => 'Unrecognized card or pass code.',
            'is_simulator' => true,
        ];
    }

    public function syncPass(string $passCode, bool $isActive): array
    {
        return [
            'pass_code' => $passCode,
            'synced' => true,
            'is_simulator' => true,
        ];
    }

    public function getStatus(): array
    {
        $turnstilesEnabled = \App\Domain\Shared\Models\SystemSetting::getBool('enable_turnstile_gate', false);

        return [
            'driver' => 'simulator',
            'label' => self::STATUS_LABEL,
            'configured' => true,
            'is_production_ready' => false,
            'enabled' => $turnstilesEnabled,
            'turnstiles_enabled' => $turnstilesEnabled,
            'mode' => $turnstilesEnabled
                ? 'Automated Optical Barrier Turnstile'
                : 'Staff-Operated Reception Desk (Turnstile Gates Standby)',
        ];
    }

    private function logEvent(?int $personId, ?int $passId, string $token, string $facility, string $type, string $reason): void
    {
        AccessEvent::create([
            'person_id' => $personId,
            'guest_pass_id' => $passId,
            'card_number' => $token,
            'facility_code' => $facility,
            'event_type' => $type,
            'reason' => $reason,
            'device_id' => 'SIMULATOR-01',
            'recorded_at' => now(),
        ]);
    }
}
