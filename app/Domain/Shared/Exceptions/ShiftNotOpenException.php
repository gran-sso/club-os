<?php

namespace App\Domain\Shared\Exceptions;

use Exception;

class ShiftNotOpenException extends Exception
{
    public function __construct(
        string $message = "Counter shift is not currently open for transactions.",
        int $code = 422
    ) {
        parent::__construct($message, $code);
    }
}
