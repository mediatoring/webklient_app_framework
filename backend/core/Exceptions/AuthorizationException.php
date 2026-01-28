<?php

declare(strict_types=1);

namespace WebklientApp\Core\Exceptions;

class AuthorizationException extends AppException
{
    protected int $statusCode = 403;
    protected string $errorCode = 'AUTHORIZATION_ERROR';
}
