<?php

declare(strict_types=1);

namespace WebklientApp\Core\Exceptions;

class AuthenticationException extends AppException
{
    protected int $statusCode = 401;
    protected string $errorCode = 'AUTHENTICATION_ERROR';
}
