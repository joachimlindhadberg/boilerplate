# V2.5.0 — Stable Release Candidate (development + production candidate archives)

- Based on the approved `V2.4.4z25a (2.4.4.113)` source. Internal software version set to `2.5.0`.
- No feature, UI, runtime schema, module manifest or upload changes. Maintains SiteBackup JSON SQL fix from z25a.
- Historical regression test version extractors migrated from four-part `2.4.4.x`-only patterns to three-/four-part semantic versions. Their individual pre-existing minimum-version checks are retained. The suite remains in the development ZIP only.
- Previous update ZIP inspector likewise recognizes three-/four-part internal versions.
- New `tests/release/production_zip.py` builds/verifies a **separate production candidate** with minimal operational CLI set and without fixtures, tests, development tools or personal data; archive bytes compared with the development candidate source.
- Add formal no-go document `docs/deployment/V2.5.0-GO-LIVE-GATE.md` and a release-specific regression test.
- Current sign-off remains **source/code/packaging only**. Separate staging/auth/restore/cron/security gate remains required before real go-live. No DB-active tests were run in this release workshop.

Local candidate verification: `sh tests/integration/run.sh verify-production` and `python3 tests/release/production_zip.py verify <zip-path>`. Both read source only. The second reads a ZIP without extracting it.
