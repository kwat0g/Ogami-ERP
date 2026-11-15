# HR07-TRAINING-COMPLETE — readiness preflight 03

**Result:** The guarded `test` command stopped during the fresh baseline migration after PostgreSQL shut down.

- **Command:** `E2E_AUTH_ACTORS=hr_officer,department_head__hr node scripts/e2e/runner.mjs test --grep HR07-TRAINING-COMPLETE`
- **Runner result:** Exit 137 during `migrate:fresh`; TypeScript checking and Playwright startup were not reached.
- **Evidence:** The migration output ended while applying the September 25 schema migrations. PostgreSQL logged a client reset and unexpected EOF at 2026-11-04 17:59:22 UTC, followed by an administrative fast-shutdown request. The isolated Compose project had no running services afterward. No runner, PHP test, or Playwright process remained active.
- **Business effects:** The disposable baseline migration was interrupted. No actor login or process UI action occurred; no transactional record was created.
- **Finding:** Additional occurrence of [ENV-E2E-002](../FINDINGS.md#env-e2e-002--e2e-runner-and-full-actor-setup-are-intermittently-terminated). Shutdown source is unknown.

No Playwright artifacts were produced because baseline setup stopped before browser startup.
