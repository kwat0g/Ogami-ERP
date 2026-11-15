# HR07-BANK-CHANGE — twelfth attempt

- **Date:** 2026-11-04
- **Journey run ID:** `1f8309f0-2197-418b-bf22-4f63c885c6f5`
- **Environment:** guarded `ogami-erp-e2e` Compose stack and freshly seeded `ogami_e2e` database
- **Invocation:** `E2E_AUTH_ACTORS=employee,hr_officer,finance_officer node scripts/e2e/runner.mjs test --grep HR07-BANK-CHANGE`
- **Result:** UI sign-in passed (3/3). Employee submitted the change, HR approved in its own session, and Finance found the pending Finance leg through its live badge/queue and approved through its own session. The Finance action returned HTTP 200; Employee saw Approved, the new bank name, and the masked account display. The final action assertion failed before read-only final-state, audit, mail, and notification checks ran.
- **Failure:** The page renders the account last four as `••••7426`; the assertion looked for a standalone `7426` text node. No product defect inferred from this locator mismatch.
- **Correction:** Assert the generated last-four digits inside the masked `definition` text (`••••<last4>`), and retain the check that the full account number is absent. Rerun from a clean baseline to execute final DB/audit and Mailpit assertions.
- **Evidence:** [archived screenshots, trace, error context, and journey context](runs/assets/hr07-bank-change-twelfth-attempt/); `spa/src/pages/self-service/profile.tsx` renders `accountLast4` in the Account definition.
- **State:** Finance likely completed the UI transaction, but final read-only assertions were blocked. Reseed the isolated database before the next attempt.
