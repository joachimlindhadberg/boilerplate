<?php

declare(strict_types=1);

/**
 * Optional module integration for future/due publication actions.
 * Times are UTC DATETIME strings. Modules own their scheduling schema,
 * validation and execution; Core owns the read-only administration UI.
 */
interface ModuleScheduledContentProviderInterface
{
    /**
     * @return array<int,array{type:string,title:string,action_type:string,due_at_utc:string,edit_url:string}>
     */
    public function scheduledContent(string $scope, int $limit): array;

    /** Number of outstanding actions (not the number of content rows). */
    public function scheduledContentCount(string $scope): int;

    /** Process due actions idempotently; called from worker or request fallback. */
    public function runScheduledContent(int $limit): int;
}
