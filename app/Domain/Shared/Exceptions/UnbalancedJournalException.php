<?php

namespace App\Domain\Shared\Exceptions;

use Exception;

class UnbalancedJournalException extends Exception
{
    public function __construct(
        int $debitPaise,
        int $creditPaise,
        string $message = "Journal entry does not balance."
    ) {
        parent::__construct("{$message} Debits: {$debitPaise} paise, Credits: {$creditPaise} paise.", 500);
    }
}
