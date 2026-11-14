<?php

declare(strict_types=1);

/**
 * Stage4d3a safety boundary (private QA only). PHP flock does not fence SQL
 * clients. MySQL DDL implicitly commits, so ordinary transaction/table locks
 * cannot protect the entire backup -> DDL -> recovery window.
 *
 * No external writer fence is currently proven for arbitrary shared hosting.
 * Until a separately validated deployment-specific fencing protocol exists,
 * refuse ALL browser-driven schema migrations. No user flag or HTTP input is
 * allowed to make this check pass.
 */
final class UpdateExternalWriterSafety
{
    public static function automaticMigrationAllowed(): bool
    {
        return false;
    }

    public static function limitation(): string
    {
        return 'External MySQL writers cannot be safely fenced by the CMS PHP maintenance lock.';
    }
}
