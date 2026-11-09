<?php

namespace App\Domain\Integrations\Contracts;

interface AccessControlProviderInterface
{
    /**
     * Authorise access through physical door or turnstile.
     */
    public function authorizeEntry(string $tokenOrCode, string $facilityCode): array;

    /**
     * Synchronize permission or pass revocation.
     */
    public function syncPass(string $passCode, bool $isActive): array;

    /**
     * Get integration hardware status.
     */
    public function getStatus(): array;
}
