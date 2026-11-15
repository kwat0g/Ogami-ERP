# HR02-HAPPY — attempt 11 (auth 401 allowlist no longer matched diagnostics evidence)

- **Date:** 2026-11-04 UTC
- **Classification:** Test issue (E2E diagnostics allowlist mismatch)
- **Actors:** HR Officer login was attempted; System Admin setup and the HR02 journey did not run.
- **Command:** `E2E_AUTH_ACTORS=hr_officer,system_admin node scripts/e2e/runner.mjs test --grep HR02-HAPPY` (guarded fresh baseline; one worker)
- **Result:** Fresh baseline, 37-actor seeding, and E2E TypeScript preflight completed. The HR Officer sign-in response was 200 and the UI reached Dashboard, but auth setup then failed its diagnostics assertion on the expected unauthenticated initial `GET /api/v1/auth/user` 401. The diagnostic helper had just changed console evidence from path-only to full-origin form; the existing allowlist entry still used the old path-only text. Auth setup stopped and the dependent process journey did not run.
- **Data state:** No HR02 process action or transactional record was created. The login context was closed without saving its storage state; the System Admin login was not attempted.
- **Correction:** Update the exact known-auth console entry to include `https://nginx/api/v1/auth/user`, consistent with the new evidence format. This retains the same single expected endpoint/status exception.
- **Evidence:** [archived auth error context, screenshots, and Playwright result](assets/hr02-happy-attempt-11-auth-noise-allowlist/).

This is a resolved E2E harness issue pending a fresh-baseline confirmation. No application code changed.
