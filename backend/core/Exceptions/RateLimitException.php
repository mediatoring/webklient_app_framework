<?php

declare(strict_types=1);

namespace WebklientApp\Core\Exceptions;

class RateLimitException extends AppException
{
    protected int $statusCode = 429;
    protected string $errorCode = 'RATE_LIMIT_EXCEEDED';
}
