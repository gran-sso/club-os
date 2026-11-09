<?php

namespace App\Domain\Shared\Exceptions;

use Exception;

class CardNotActiveException extends Exception
{
    public function __construct(
        string $message = "Card is not active, has been blocked, or replaced.",
        int $code = 422
    ) {
        parent::__construct($message, $code);
    }
}
