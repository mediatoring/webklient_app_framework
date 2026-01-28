<?php

declare(strict_types=1);

namespace WebklientApp\Core\Exceptions;

class ValidationException extends AppException
{
    protected int $statusCode = 400;
    protected string $errorCode = 'VALIDATION_ERROR';
}
