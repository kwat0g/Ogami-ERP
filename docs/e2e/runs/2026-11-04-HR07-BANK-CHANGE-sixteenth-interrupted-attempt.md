# HR07-BANK-CHANGE — sixteenth attempt (environment interrupted)

- **Date:** 2026-11-04
- **Environment:** guarded `ogami-erp-e2e` stack; fresh baseline was seeded before the test invocation
- **Invocation:** `E2E_AUTH_ACTORS=employee,hr_officer,finance_officer node scripts/e2e/runner.mjs test --grep HR07-BANK-CHANGE`
- **Result:** The auth-setup Playwright test passed and saved sessions for the three selected actors. The outer runner was terminated with exit 143 while the HR-07 journey was in progress. Playwright did not produce a result for the journey; no business-step pass/fail, transaction status, or artifact can be claimed.
- **Environment evidence:** The invocation rebuilt/health-started the full Compose stack, built the one-off browser image, and type-checked before the two-test Playwright run. At termination, the Compose services received shutdown signals and the one-off runner was stopped. The prior result JSON remained timestamped from the fifteenth attempt, so it is not evidence for this invocation.
- **Workaround now in place:** For `runner.mjs test`, a complete set of running E2E services is reused through Compose's health wait without rebuilding the application stack. Missing services still trigger a build/start. The readiness helper has unit coverage; the reduced-duration journey run is pending.
- **Next action:** Start the guarded stack, seed again to remove any unobserved partial UI effects, then rerun once. No PHP suite or concurrent journey was run.
