# HR07-BANK-CHANGE — first attempt

- **Date:** 2026-11-04
- **Journey run ID:** `e51f4a66-9779-4ceb-8781-cd9364658141`
- **Environment:** guarded `ogami-erp-e2e` Compose stack and `ogami_e2e` database
- **Invocation:** `E2E_AUTH_ACTORS=employee,hr_officer,finance_officer node scripts/e2e/runner.mjs test --grep HR07-BANK-CHANGE`
- **Result:** selected-actor real-UI auth setup passed (3/3); business journey failed in its first navigation step. No profile-change request was submitted; approval steps were blocked.
- **Failure:** The employee test opened `/`, which is the public landing page, and then expected the authenticated Employee dashboard action “My profile.” Playwright’s captured accessibility tree shows the public marketing site and sign-in link. No business record was created or advanced.
- **Classification:** Test issue `TST-HR-001`; no product defect inferred.
- **Correction proposed after this attempt:** Start from `/dashboard`, the post-sign-in landing route. The in-app employee profile path is still being traced; a follow-up attempt checks the actual authenticated menu.
- **Evidence:** [archived trace, screenshots, context, and error context](runs/assets/hr07-bank-change-first-attempt/); `spa/e2e-process/hr-profile.process.spec.ts`.
- **Next:** Re-seed the isolated database and rerun this journey alone.
