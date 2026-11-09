#!/usr/bin/env python3
"""Stable-prep release inventory/negative-control regression. No installation or DB.

Creates and verifies one disposable candidate ZIP from the current checkout.
The result is deliberately NOT a final release artifact.
"""
from __future__ import annotations

import sys
import tempfile
from pathlib import Path
from zipfile import ZipFile

sys.dont_write_bytecode = True

import production_zip as production
import install_update_preflight as preflight


def verify(condition: bool, detail: str) -> None:
    if not condition:
        raise AssertionError(detail)
    print('PASS:', detail)


def rejects(call, expected: str) -> None:
    try:
        call()
    except (ValueError, preflight.PreflightFailure) as exc:
        verify(expected.lower() in str(exc).lower(), 'Reject: ' + expected)
    else:
        raise AssertionError('Unsafe case passed: ' + expected)


def main() -> None:
    root = production.ROOT
    required = {
        'database/updates/20261105_000_page_access.php',
        'core/classes/PageAccess.php',
        'core/classes/UpdateRetiredFilePlan.php',
        'core/classes/UpdateAdminNotification.php',
    }
    verify(required <= production.REQUIRED, 'New migration and runtime dependencies are required')
    s1_qa_classes = {
        'UpdateBrowserMigrationRehearsal',
        'UpdateBrowserRecoveryAuthorizationQa',
        'UpdateDatabaseCutoverRehearsal',
        'UpdateDatabaseIsolatedRestore',
        'UpdateNativeMigrationRehearsal',
    }
    s1_qa_docs = {
        'docs/deployment/F4C3-DATABASE-CUTOVER-QA1.md',
        'docs/deployment/F4C4-BROWSER-MIGRATION-QA1.md',
        'docs/deployment/F4C5-NATIVE-MYSQL-MIGRATION-QA1.md',
        'docs/deployment/F4C6-BROWSER-RECOVERY-AUTHORIZATION-QA1.md',
        'docs/deployment/F4C7-REAL-CMS-AUTH-RECOVERY-QA1.md',
        'docs/deployment/F4C8-FIRST-INSTALL-BASELINE-QA1.md',
        'docs/deployment/F4C9-INSTALL-UPDATE-INTEGRATION-QA1.md',
        'docs/deployment/F4C10-RECOVERY-COMPLETION-QA1.md',
        'docs/deployment/STABLE-S1-PRODUCTION-ZIP-CLEANUP-QA1.md',
    }
    verify(s1_qa_classes <= production.DEV_ONLY_UPDATE_CLASSES,
           'S1 QA-only classes included in exclusion policy')
    verify(s1_qa_docs <= production.DEV_ONLY_DEPLOYMENT_DOCS,
           'S1 internal QA documents included in exclusion policy')
    verify(all((root / p).is_file() for p in s1_qa_docs),
           'S1 QA documents exist in development checkout')
    verify(all((root / 'core/classes' / (name + '.php')).is_file() for name in s1_qa_classes),
           'S1 QA classes exist in development checkout')
    with tempfile.TemporaryDirectory(prefix='bp-stable-prep-release-qa-') as temp:
        artifact = Path(temp) / 'candidate.zip'
        production.build(artifact)
        production.verify(artifact)
        with ZipFile(artifact) as z:
            names = set(z.namelist())
            entries = {n.removeprefix(production.TOP) for n in names}
            files = {x for x in entries if x and not x.endswith('/')}
            dirs = {x for x in entries if x.endswith('/')}
            verify(required <= files, 'New migration and required PHP classes included in candidate')
            verify(not any(x.startswith('tests/') for x in entries), 'Development tests excluded')
            verify(not any(x.startswith('.git/') or '/.git/' in x for x in entries), 'Git internals excluded')
            verify(not any(x.endswith('.env') or '.env.' in x for x in entries), 'Known environment-secret names excluded')
            verify(not any(x.startswith('public_html/uploads/') and x != 'public_html/uploads/.htaccess' for x in files),
                   'Upload directory contains only security policy')
            verify('public_html/uploads/' in dirs, 'Runtime uploads directory included')
            for name in sorted(s1_qa_classes):
                path = 'core/classes/' + name + '.php'
                verify(path not in files, 'S1 excluded private QA class: ' + name)
                verify(production.omitted(path), 'S1 omission predicate: ' + name)
            for path in sorted(s1_qa_docs):
                verify(path not in files, 'S1 excluded development QA documentation: ' + path)
            verify('core/classes/UpdateOneClickInstaller.php' in files,
                   'Production code-only updater preserved')
            verify('core/classes/UpdateDurableRecoveryGate.php' in files,
                   'Production fail-closed recovery gate preserved')
            verify('core/classes/UpdateMaintenanceGate.php' in files,
                   'Production maintenance admission preserved')
            rejects(lambda: production.assert_consistency(
                files | {'core/classes/UpdateBrowserMigrationRehearsal.php'}, dirs),
                'Development-only content leaked')
            rejects(lambda: production.assert_consistency(
                files | {'docs/deployment/F4C10-RECOVERY-COMPLETION-QA1.md'}, dirs),
                'Development-only content leaked')
            verify(not any(x.startswith('public_html/assets/uploads/') for x in entries),
                   'Legacy uploads excluded')
            for rel in required:
                verify(z.read(production.TOP + rel) == (root / rel).read_bytes(), 'Exact source bytes: ' + rel)
            rejects(lambda: production.assert_consistency(files - {'database/updates/20261105_000_page_access.php'}, dirs),
                    '20261105_000_page_access.php')
            rejects(lambda: production.assert_consistency(files - {'core/classes/PageAccess.php'}, dirs),
                    'PageAccess.php')
            rejects(lambda: preflight.check_path('boilerplate/../private.php', False), 'Unsafe path components')
            verify(preflight.prohibited('public_html/uploads/visitor-private.php', False),
                   'Direct upload executable excluded')
            verify(preflight.prohibited('core/.env', False), 'Private environment file prohibited')
        print('RESULT: Stable-prep production candidate build and negative controls PASS')
        print('NOTE: Candidate discarded; this test does not install, publish or approve a release')


if __name__ == '__main__':
    main()
