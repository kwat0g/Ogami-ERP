# HR03-OVERTIME-APPROVE — readiness preflight 06

**Result:** The guarded runner refused to start Playwright because the dedicated E2E stack was stopped.

- **Command:** `E2E_AUTH_ACTORS=employee,department_head node scripts/e2e/runner.mjs test --grep HR03-OVERTIME-APPROVE`
- **Runner result:** Exit 1 before TypeScript compilation or browser startup.
- **Evidence:** The runner reported that the isolated stack was not fully running. A read-only, explicitly scoped Compose status then showed no running E2E services. No Playwright process or PHP test process was active.
- **Business effects:** No actor logged in and no transactional record was created. The preceding guarded seed had created a fresh baseline; the follow-up will re-seed after restoring stack health.
- **Finding:** Additional occurrence of [ENV-E2E-002](../FINDINGS.md#env-e2e-002--e2e-runner-and-full-actor-setup-are-intermittently-terminated). The stack's shutdown source is unknown.

No browser artifacts were produced because the readiness guard stopped before Playwright startup.
