<?php

declare(strict_types=1);

namespace WebklientApp\Core\Exceptions;

use WebklientApp\Core\Http\JsonResponse;

class ExceptionHandler
{
    public static function handle(\Throwable $e): void
    {
        $debug = filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN);
        $response = self::toResponse($e, $debug);
        $response->send();

        self::logException($e);
    }

    public static function toResponse(\Throwable $e, bool $debug = false): JsonResponse
    {
        $statusCode = 500;
        $errorCode = 'INTERNAL_ERROR';
        $details = [];

        if ($e instanceof AppException) {
            $statusCode = $e->getStatusCode();
            $errorCode = $e->getErrorCode();
            $details = $e->getDetails();
        }

        $body = [
            'success' => false,
            'error' => [
                'code' => $errorCode,
                'message' => $e->getMessage() ?: 'An unexpected error occurred.',
                'details' => $details,
            ],
            'request_id' => self::getRequestId(),
        ];

        if ($debug && !($e instanceof AppException && $statusCode < 500)) {
            $body['error']['trace'] = $e->getTraceAsString();
            $body['error']['file'] = $e->getFile();
            $body['error']['line'] = $e->getLine();
        }

        return new JsonResponse($body, $statusCode);
    }

    private static function logException(\Throwable $e): void
    {
        $logDir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($logDir)) {
            return;
        }

        $entry = sprintf(
            "[%s] %s: %s in %s:%d\n%s\n\n",
            date('Y-m-d H:i:s'),
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );

        @file_put_contents($logDir . '/error.log', $entry, FILE_APPEND | LOCK_EX);
    }

    private static function getRequestId(): string
    {
        static $id = null;
        if ($id === null) {
            $id = bin2hex(random_bytes(8));
        }
        return $id;
    }
}
