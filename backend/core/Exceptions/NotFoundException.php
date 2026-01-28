<?php

declare(strict_types=1);

namespace WebklientApp\Core\Exceptions;

class NotFoundException extends AppException
{
    protected int $statusCode = 404;
    protected string $errorCode = 'NOT_FOUND';
}
