<?php

declare(strict_types=1);

namespace App\Core;

/** Thrown to stop a request with an HTTP error page (404, 403, 419 and so on). */
final class HttpException extends \RuntimeException
{
    public function __construct(public readonly int $status, string $message = '')
    {
        parent::__construct($message, $status);
    }
}
