<?php

namespace App\Domain\Shared\Exceptions;

use Exception;

class InsufficientFundsException extends Exception
{
    public function __construct(
        string $message = "Insufficient prepaid wallet funds for this operation.",
        int $code = 422
    ) {
        parent::__construct($message, $code);
    }
}
