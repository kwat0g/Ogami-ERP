# HR07-BANK-CHANGE — seventh attempt

- **Date:** 2026-11-04
- **Journey run ID:** `5ca61355-70e7-4d30-aff3-e324706fec28`
- **Environment:** guarded `ogami-erp-e2e` Compose stack and freshly seeded `ogami_e2e` database
- **Invocation:** `E2E_AUTH_ACTORS=employee,hr_officer,finance_officer node scripts/e2e/runner.mjs test --grep HR07-BANK-CHANGE`
- **Result:** selected-actor auth passed (3/3); Employee and HR opened their screens. Finance reached the authenticated Dashboard shell but that role's dashboard had no accessible heading; the test's generic first-heading check stopped setup before the Finance queue was opened. No transaction was submitted.
- **Classification:** Test issue `TST-HR-006`; no product defect inferred.
- **Correction:** Verify the authenticated shell by its visible Modules navigation and profile menu, then click Finance's queue through its sidebar.
- **Evidence:** [archived trace, screenshots, context, and error context](runs/assets/hr07-bank-change-seventh-attempt/); `spa/e2e-process/hr-profile.process.spec.ts`.
- **Next:** Re-seed the isolated database and rerun this journey alone.
