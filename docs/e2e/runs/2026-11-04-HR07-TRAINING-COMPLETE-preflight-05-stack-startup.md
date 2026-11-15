# HR07-TRAINING-COMPLETE — readiness preflight 05

**Result:** Guarded stack startup exited before readiness; the test command did not run.

- **Command:** `node scripts/e2e/runner.mjs up && node scripts/e2e/runner.mjs wait && E2E_AUTH_ACTORS=hr_officer,department_head__hr node scripts/e2e/runner.mjs test --grep HR07-TRAINING-COMPLETE`
- **Runner result:** `runner up` exited 1 with `dependency failed to start: container ogami-erp-e2e-api-1 exited (0)`. The guarded `wait` and Playwright test were not reached.
- **Evidence:** Exact-project inspection showed the newly started API and database exited 0 and SPA exited 143; Reverb and scheduler exited 131. API logs show PHP-FPM entered its normal “Finishing ... exiting” shutdown sequence. The shutdown source was not identified. No fresh baseline migration, browser, or process UI action occurred.
- **Finding:** Additional occurrence of [ENV-E2E-002](../FINDINGS.md#env-e2e-002--e2e-runner-and-full-actor-setup-are-intermittently-terminated).

The next attached `up` → `wait` → `test` sequence reached the browser and produced the recorded attempt 10 result.
