<?php

namespace App\Domain\Integrations\Adapters;

use App\Domain\Integrations\Contracts\CardReaderInterface;

class KeyboardCardReader implements CardReaderInterface
{
    public function parseInput(string $rawInput): ?string
    {
        $trimmed = trim($rawInput);
        if (empty($trimmed)) {
            return null;
        }

        // Clean magnetic stripe sentinels if present (%B...?, ;...?)
        if (preg_match('/[%;]([a-zA-Z0-9_\-]+)\?/', $trimmed, $matches)) {
            return $matches[1];
        }

        // Clean RFID / Barcode alphanumeric code
        return preg_replace('/[^a-zA-Z0-9_\-]/', '', $trimmed);
    }

    public function getStatus(): array
    {
        return [
            'driver' => 'keyboard_emulation',
            'label' => 'Standard USB / Keyboard Card Reader',
            'configured' => true,
            'is_production_ready' => true,
        ];
    }
}
