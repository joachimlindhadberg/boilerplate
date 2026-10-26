<?php

declare(strict_types=1);

/** Explicit output contexts. Use url() for href/src, not text(). */
final class AdminEscaper
{
    public static function text(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function attribute(mixed $value): string
    {
        return self::text($value);
    }

    public static function safeUrl(string $value): ?string
    {
        if ($value === '' || trim($value) !== $value
            || preg_match('/[\x00-\x20\x7f]/', $value) || str_contains($value, chr(92))
            || str_starts_with($value, '//')) {
            return null;
        }
        // Prefer local root-relative links. Backslash and protocol-relative forms rejected.
        if (str_starts_with($value, '/')) {
            return $value;
        }
        $parts = parse_url($value);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])
            || !in_array(strtolower((string)$parts['scheme']), ['http', 'https'], true)) {
            return null;
        }
        if (isset($parts['user']) || isset($parts['pass']) || $parts['host'] === '') {
            return null;
        }
        return $value;
    }

    public static function url(string $value): string
    {
        return self::attribute(self::safeUrl($value) ?? '#');
    }
}
