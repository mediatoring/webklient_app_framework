<?php

declare(strict_types=1);

namespace WebklientApp\Core\Validation;

use WebklientApp\Core\Exceptions\ValidationException;

class Validator
{
    private array $errors = [];

    /**
     * Validate data against rules.
     *
     * Rules format: ['field' => 'required|string|min:3|max:255']
     */
    public function validate(array $data, array $rules): array
    {
        $this->errors = [];
        $validated = [];

        foreach ($rules as $field => $ruleString) {
            $fieldRules = is_array($ruleString) ? $ruleString : explode('|', $ruleString);
            $value = $data[$field] ?? null;
            $isRequired = in_array('required', $fieldRules);
            $isNullable = in_array('nullable', $fieldRules);

            if ($value === null || $value === '') {
                if ($isRequired) {
                    $this->addError($field, 'required', 'This field is required.');
                }
                if ($isNullable || !$isRequired) {
                    $validated[$field] = $value;
                }
                continue;
            }

            $valid = true;
            foreach ($fieldRules as $rule) {
                if (in_array($rule, ['required', 'nullable', 'optional'])) {
                    continue;
                }

                $params = [];
                if (str_contains($rule, ':')) {
                    [$rule, $paramStr] = explode(':', $rule, 2);
                    $params = explode(',', $paramStr);
                }

                $method = 'rule' . ucfirst($rule);
                if (method_exists($this, $method)) {
                    if (!$this->$method($field, $value, $params)) {
                        $valid = false;
                    }
                }
            }

            if ($valid) {
                $validated[$field] = $value;
            }
        }

        if (!empty($this->errors)) {
            throw new ValidationException('Validation failed.', $this->errors);
        }

        return $validated;
    }

    private function addError(string $field, string $rule, string $message): void
    {
        $this->errors[] = ['field' => $field, 'rule' => $rule, 'message' => $message];
    }

    private function ruleString(string $field, mixed $value, array $params): bool
    {
        if (!is_string($value)) {
            $this->addError($field, 'string', 'Must be a string.');
            return false;
        }
        return true;
    }

    private function ruleInteger(string $field, mixed $value, array $params): bool
    {
        if (!is_numeric($value) || (int)$value != $value) {
            $this->addError($field, 'integer', 'Must be an integer.');
            return false;
        }
        return true;
    }

    private function ruleBoolean(string $field, mixed $value, array $params): bool
    {
        if (!in_array($value, [true, false, 0, 1, '0', '1', 'true', 'false'], true)) {
            $this->addError($field, 'boolean', 'Must be a boolean.');
            return false;
        }
        return true;
    }

    private function ruleEmail(string $field, mixed $value, array $params): bool
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, 'email', 'Must be a valid email.');
            return false;
        }
        return true;
    }

    private function ruleUrl(string $field, mixed $value, array $params): bool
    {
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            $this->addError($field, 'url', 'Must be a valid URL.');
            return false;
        }
        return true;
    }

    private function ruleMin(string $field, mixed $value, array $params): bool
    {
        $min = (int)($params[0] ?? 0);
        $length = is_string($value) ? mb_strlen($value) : $value;
        if ($length < $min) {
            $this->addError($field, 'min', "Must be at least {$min}.");
            return false;
        }
        return true;
    }

    private function ruleMax(string $field, mixed $value, array $params): bool
    {
        $max = (int)($params[0] ?? PHP_INT_MAX);
        $length = is_string($value) ? mb_strlen($value) : $value;
        if ($length > $max) {
            $this->addError($field, 'max', "Must be at most {$max}.");
            return false;
        }
        return true;
    }

    private function ruleIn(string $field, mixed $value, array $params): bool
    {
        if (!in_array($value, $params)) {
            $this->addError($field, 'in', 'Invalid value. Allowed: ' . implode(', ', $params));
            return false;
        }
        return true;
    }

    private function ruleRegex(string $field, mixed $value, array $params): bool
    {
        $pattern = $params[0] ?? '';
        if (!preg_match($pattern, (string)$value)) {
            $this->addError($field, 'regex', 'Format is invalid.');
            return false;
        }
        return true;
    }

    private function ruleArray(string $field, mixed $value, array $params): bool
    {
        if (!is_array($value)) {
            $this->addError($field, 'array', 'Must be an array.');
            return false;
        }
        return true;
    }
}
