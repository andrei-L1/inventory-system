<?php

namespace App\Exceptions;

use Exception;

class InsufficientStockException extends Exception
{
    /**
     * @param string $message
     * @param int $code
     * @param Exception|null $previous
     */
    public function __construct($message = "Insufficient stock available to complete this transaction.", $code = 422, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
