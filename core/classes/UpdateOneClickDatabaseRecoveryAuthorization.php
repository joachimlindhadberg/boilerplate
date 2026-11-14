<?php

declare(strict_types=1);

/**
 * Short-lived independent capability, no Db/Auth/SessionManager dependency.
 * SECURITY: token + CSRF/Origin admission does NOT authorize destructive SQL;
 * the separate controller must revalidate bundle, exact journal, scope and lock.
 * No public recovery controller or cookie issuance is enabled in Stage 4c1.
 */
final class UpdateOneClickDatabaseRecoveryAuthorization
{
    public const COOKIE = '__Host-bp-recovery';

    public function __construct(private UpdateOneClickDatabaseJournal $journal) {}

    /** @return array{name:string,value:string,options:array<string,mixed>} */
    public function preparedBrowserCookie(string $id, string $secret): array
    {
        $record = $this->journal->inspect($id);
        if (($record['phase'] ?? null) !== 'prepared'
            || !$this->journal->verifyRecoverySecret($id, $secret)
            || !is_int($record['recovery_expires_at'] ?? null)
            || $record['recovery_expires_at'] <= time()
            || $record['recovery_expires_at'] > time()+3600)
            throw new RuntimeException('Recovery cookie cannot be provisioned.');
        return ['name' => self::COOKIE, 'value' => $id . '.' . $secret,
            'options' => ['expires' => $record['recovery_expires_at'], 'path' => '/',
                'secure' => true, 'httponly' => true, 'samesite' => 'Strict']];
    }

    public static function csrf(string $id, string $secret): string
    {
        if (preg_match('/\A[a-f0-9]{32}\z/D', $id) !== 1
            || preg_match('/\A[a-f0-9]{64}\z/D', $secret) !== 1)
            throw new RuntimeException('Invalid recovery secret.');
        return hash_hmac('sha256', "bp-db-recovery-csrf-v1\0".$id, hex2bin($secret));
    }

    public function allowsRecoveryPost(string $id, string $cookie, string $csrf,
        string $method, string $requestOrigin, string $trustedOrigin): bool
    {
        // Origin MUST match the configured canonical HTTPS origin, never Host.
        if ($method !== 'POST' || !preg_match('/\A[a-f0-9]{64}\z/D', $csrf)
            || !preg_match('/\A[a-f0-9]{32}\.[a-f0-9]{64}\z/D', $cookie)
            || !preg_match('~\Ahttps://[A-Za-z0-9.-]+(?::[0-9]{1,5})?\z~D', $trustedOrigin)
            || !hash_equals($trustedOrigin, $requestOrigin)) return false;
        [$target, $secret] = explode('.', $cookie, 2);
        if (!hash_equals($id, $target)) return false;
        try {
            $journal = $this->journal->inspect($id);
            if (($journal['blocked'] ?? null) !== true
                || ($journal['install_ready'] ?? null) !== false
                || !in_array($journal['phase'] ?? null,
                    ['database_running','recovery_required','recovering'], true)
                || !is_int($journal['recovery_expires_at'] ?? null)
                || $journal['recovery_expires_at'] <= time()
                || !$this->journal->verifyRecoverySecret($id, $secret)) return false;
            return hash_equals(self::csrf($id, $secret), $csrf);
        } catch (Throwable $e) { return false; }
    }
}
