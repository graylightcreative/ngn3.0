<?php

namespace NGN\Lib\AI;

/**
 * Custom exception for forbidden actions due to subscription level.
 */
class ForbiddenException extends \Exception
{
    public function __construct(string $message = "Access denied due to subscription level.", int $code = 403, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
