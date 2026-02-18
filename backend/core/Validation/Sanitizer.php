<?php

declare(strict_types=1);

namespace WebklientApp\Core\Validation;

class Sanitizer
{
    public static function string(mixed $value): string
    {
        $str = str_replace("\0", '', (string) $value);
        return htmlspecialchars(trim($str), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function email(string $value): string
    {
        return strtolower(trim($value));
    }

    public static function url(string $value): string
    {
        $url = filter_var(trim($value), FILTER_SANITIZE_URL) ?: '';
        return htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function integer(mixed $value): int
    {
        return intval($value);
    }

    public static function filename(string $value): string
    {
        $value = str_replace("\0", '', $value);
        $value = basename($value);
        $value = preg_replace('/[^a-zA-Z0-9._-]/', '', $value);
        $value = preg_replace('/\.{2,}/', '.', $value);
        return ltrim($value, '.');
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
