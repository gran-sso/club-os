<?php

namespace App\Domain\Integrations\Contracts;

interface CardReaderInterface
{
    /**
     * Clean and normalize raw reader input or keyboard swipe.
     */
    public function parseInput(string $rawInput): ?string;

    /**
     * Get device status.
     */
    public function getStatus(): array;
}
