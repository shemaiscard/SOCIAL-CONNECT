<?php

declare(strict_types=1);

namespace App\Core;

/** A user-facing input problem. The message is safe to show. */
final class ValidationError extends \RuntimeException
{
}
