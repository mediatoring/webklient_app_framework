<?php

declare(strict_types=1);

namespace WebklientApp\Core\Exceptions;

abstract class AppException extends \RuntimeException
{
    protected int $statusCode = 500;
    protected string $errorCode = 'INTERNAL_ERROR';
    protected array $details = [];

    public function __construct(string $message = '', array $details = [], int $code = 0, ?\Throwable $previous = null)
    {
        $this->details = $details;
        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getDetails(): array
    {
        return $this->details;
    }
}
