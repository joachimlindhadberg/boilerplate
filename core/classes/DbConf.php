<?php

final class DbConf
{
    private static function required(string $key, string $developmentDefault): string
    {
        $value = AppConfig::get($key, AppConfig::isProduction() ? null : $developmentDefault);
        $value = trim((string)$value);

        if ($value === '') {
            throw new RuntimeException($key . ' must be configured in production.');
        }

        return $value;
    }

    public static function host(): string
    {
        return self::required('DB_HOST', 'localhost');
    }

    public static function port(): int
    {
        $value = AppConfig::get('DB_PORT', AppConfig::isProduction() ? null : '3306');
        $port = (int)$value;
        if ($port < 1) {
            throw new RuntimeException('DB_PORT must be configured with a valid port.');
        }
        return $port;
    }

    public static function user(): string
    {
        return self::required('DB_USER', 'root');
    }

    public static function pass(): string
    {
        return self::required('DB_PASS', 'password');
    }

    public static function name(): string
    {
        return self::required('DB_NAME', 'boilerplate');
    }
}
