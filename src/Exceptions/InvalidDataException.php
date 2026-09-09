<?php

declare(strict_types=1);

namespace Ccharz\DtoLite\Exceptions;

use Exception;
use Throwable;

class InvalidDataException extends Exception
{
    public function __construct(string $message = 'Invalid data to make data transfer object', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
