<?php

declare(strict_types=1);

namespace WebklientApp\Core\Validation;

class Sanitizer
{
    public static function string(mixed $value): string
    {
        return htmlspecialchars(trim((string) $value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function email(string $value): string
    {
        return strtolower(trim($value));
    }

    public static function url(string $value): string
    {
        return filter_var(trim($value), FILTER_SANITIZE_URL) ?: '';
    }

    public static function integer(mixed $value): int
    {
        return (int) filter_var($value, FILTER_SANITIZE_NUMBER_INT);
    }

    public static function filename(string $value): string
    {
        return preg_replace('/[^a-zA-Z0-9._-]/', '', $value);
    }

    /**
     * Recursively sanitize all string values in an array.
     */
    public static function array(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $result[$key] = self::array($value);
            } elseif (is_string($value)) {
                $result[$key] = self::string($value);
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }
}
