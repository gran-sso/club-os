<?php

namespace App\Domain\Shared\Exceptions;

use Exception;

class UnauthorizedActionException extends Exception
{
    public function __construct(
        string $message = "Action denied by Malabar Club business security rules.",
        int $code = 403
    ) {
        parent::__construct($message, $code);
    }
}
