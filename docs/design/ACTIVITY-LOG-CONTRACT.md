# Activity Log contract

- Use `admin_log_activity($action, $subjectType, $subjectId, $safeLabel)` **only after a completed authorized admin operation**. Never log on GET or before validation.
- Modules use the same core function, not a separate event store or frontend CSS.
- Actions and subject types are stable ASCII identifiers; translate them at display time through core language keys. English is the default, Danish is supported.
- `subject_label` contains only a safe display label, never a serialized payload or customer/form data. Keep events coarse-grained and privacy conscious.
- Scheduled background operations call `ActivityLog::record(..., null)` with a null actor; keep calls after actual database mutations.
- UI is read-only, uses core table/toolbar styles, displays at most 5 latest on Dashboard and paginated history under Tools.
- Write failures must be logged server-side and must not report a completed admin operation as failed.
- Do not treat this operational history as a tamper-proof or legally complete financial/security audit.
