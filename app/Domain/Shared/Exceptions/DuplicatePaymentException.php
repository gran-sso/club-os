<?php

namespace App\Domain\Shared\Exceptions;

use Exception;

class DuplicatePaymentException extends Exception
{
    public function __construct(
        string $message = "Duplicate transaction or idempotency key detected with conflicting payload.",
        int $code = 409
    ) {
        parent::__construct($message, $code);
    }
}
