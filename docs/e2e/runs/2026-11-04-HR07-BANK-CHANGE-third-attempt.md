# HR07-BANK-CHANGE — third attempt

- **Date:** 2026-11-04
- **Journey run ID:** `39595755-8918-438a-841a-1b78394f91e6`
- **Environment:** guarded `ogami-erp-e2e` Compose stack and freshly seeded `ogami_e2e` database
- **Invocation:** `E2E_AUTH_ACTORS=employee,hr_officer,finance_officer node scripts/e2e/runner.mjs test --grep HR07-BANK-CHANGE`
- **Result:** selected-actor real-UI auth setup passed (3/3). Employee profile and HR queue opened through the UI; the first process step stopped on the empty-state assertion, before any transaction submission. Finance and all approval steps were blocked.
- **Failure:** `getByText('No requests', { exact: true })` matched duplicate responsive empty-state headings in the rendered table. This is a Playwright locator issue, not an application defect.
- **Correction:** Use the visible accessible heading locator `getByRole('heading', { name: 'No requests', exact: true })`.
- **Evidence:** [archived trace, screenshots, context, and error context](runs/assets/hr07-bank-change-third-attempt/); `spa/e2e-process/hr-profile.process.spec.ts`.
- **Next:** Re-seed the isolated database and rerun this journey alone.
