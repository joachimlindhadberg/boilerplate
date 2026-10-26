<?php

declare(strict_types=1);

/**
 * Typed input contract for new admin routes and module controllers.
 * Validation is explicit and never treats sanitized input as authorization.
 */
final class AdminRequest
{
    /**
     * @param array<string,mixed> $source Raw query/form data.
     * @param array<string,array<string,mixed>> $schema Per-field type, required, min, max, values.
     * @return array<string,mixed> Only fields declared in the schema are returned.
     * @throws InvalidArgumentException
     */
    public static function validate(array $source, array $schema): array
    {
        $result = [];
        foreach ($schema as $field => $rules) {
            if (!is_string($field) || $field === '' || !is_array($rules)) {
                throw new InvalidArgumentException('Invalid request schema.');
            }
            $required = ($rules['required'] ?? false) === true;
            if (!array_key_exists($field, $source)) {
                if ($required) {
                    throw new InvalidArgumentException('Missing required field: ' . $field);
                }
                if (array_key_exists('default', $rules)) {
                    $result[$field] = $rules['default'];
                }
                continue;
            }
            $value = $source[$field];
            $type = $rules['type'] ?? 'string';
            if (!is_string($type)) {
                throw new InvalidArgumentException('Invalid field type.');
            }
            if ($type === 'bool') {
                if (!is_bool($value) && !is_string($value) && !is_int($value)) {
                    throw new InvalidArgumentException('Invalid boolean: ' . $field);
                }
                if (!in_array($value, [true, false, 0, 1, '0', '1'], true)) {
                    throw new InvalidArgumentException('Invalid boolean: ' . $field);
                }
                $result[$field] = $value === true || $value === 1 || $value === '1';
                continue;
            }
            if (!is_string($value) && !is_int($value)) {
                // Reject arrays/objects rather than allowing PHP coercion notices.
                throw new InvalidArgumentException('Invalid scalar: ' . $field);
            }
            $raw = (string)$value;
            if ($type === 'int' || $type === 'id') {
                if (!preg_match('/^(?:0|[1-9][0-9]*)$/D', $raw)) {
                    throw new InvalidArgumentException('Invalid integer: ' . $field);
                }
                // Explicit range check before casting to avoid integer overflow.
                $number = filter_var($raw, FILTER_VALIDATE_INT);
                if ($number === false || ($type === 'id' && $number < 1)
                    || $number < (int)($rules['min'] ?? ($type === 'id' ? 1 : 0))
                    || $number > (int)($rules['max'] ?? PHP_INT_MAX)) {
                    throw new InvalidArgumentException('Integer out of range: ' . $field);
                }
                $result[$field] = $number;
                continue;
            }
            if (!in_array($type, ['string', 'enum', 'email', 'url', 'date'], true)) {
                throw new InvalidArgumentException('Unknown field type: ' . $field);
            }
            if (($rules['trim'] ?? true) !== false) {
                $raw = trim($raw);
            }
            if (strlen($raw) < (int)($rules['min'] ?? ($required ? 1 : 0))
                || strlen($raw) > (int)($rules['max'] ?? 65535)
                || str_contains($raw, "\0")) {
                throw new InvalidArgumentException('Invalid field length: ' . $field);
            }
            if ($type === 'enum' && (!is_array($rules['values'] ?? null)
                || !in_array($raw, $rules['values'], true))) {
                throw new InvalidArgumentException('Invalid choice: ' . $field);
            }
            if ($type === 'email' && !filter_var($raw, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Invalid email: ' . $field);
            }
            if ($type === 'url' && AdminEscaper::safeUrl($raw) === null) {
                throw new InvalidArgumentException('Invalid URL: ' . $field);
            }
            if ($type === 'date') {
                $date = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
                if (!$date || $date->format('Y-m-d') !== $raw) {
                    throw new InvalidArgumentException('Invalid date: ' . $field);
                }
            }
            $result[$field] = $raw;
        }
        return $result;
    }

    public static function query(array $schema): array
    {
        return self::validate($_GET, $schema);
    }

    public static function form(array $schema): array
    {
        return self::validate($_POST, $schema);
    }

    /** Require CSRF for mutations; only the session-scoped token is accepted. */
    public static function csrfValid(mixed $token): bool
    {
        return function_exists('admin_verify_csrf') && admin_verify_csrf($token);
    }
}
