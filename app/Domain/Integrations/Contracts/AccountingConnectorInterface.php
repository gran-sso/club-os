<?php

namespace App\Domain\Integrations\Contracts;

interface AccountingConnectorInterface
{
    /**
     * Preview export data without delivering to accounting package.
     */
    public function exportPreview(array $journalHeaderIds): array;

    /**
     * Deliver vouchers to external accounting system.
     */
    public function sendVouchers(array $journalHeaderIds): array;

    /**
     * Check status and connectivity of accounting connector.
     */
    public function getStatus(): array;
}
