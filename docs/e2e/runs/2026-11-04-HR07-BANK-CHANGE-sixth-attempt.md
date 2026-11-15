# HR07-BANK-CHANGE — sixth attempt

- **Date:** 2026-11-04
- **Journey run ID:** `164da79b-9efb-4eb2-9760-585d6c69012c`
- **Environment:** guarded `ogami-erp-e2e` Compose stack and freshly seeded `ogami_e2e` database
- **Invocation:** `E2E_AUTH_ACTORS=employee,hr_officer,finance_officer node scripts/e2e/runner.mjs test --grep HR07-BANK-CHANGE`
- **Result:** selected-actor UI sign-in passed (3/3); employee submitted the bank-change request; HR saw its live `Pending HR` row and count-1 sidebar badge, opened the badge menu, and selected “Awaiting my action.” The test then stopped on the query-string expectation. No approval was performed; Finance and later steps were blocked.
- **Observed product behavior:** The badge link opens `/hr/profile-update-requests` without `?signal=awaiting_my_action`. The row remains present in the page, so the queue is reachable. `Sidebar.behavior.test.tsx` expects the actionable signal query. This is recorded as low-severity UX issue `UX-HR-001`; no business-process gap is inferred.
- **Classification of test stop:** Test issue `TST-HR-005` (the E2E assertion assumed the unit-test intended query was implemented); UX issue `UX-HR-001` recorded separately.
- **Correction:** Assert the actual HR queue route and continue by locating the row in the visible approval queue. The missing query filter remains in `FINDINGS.md` and the catalog observation; no application code is changed.
- **Evidence:** [archived trace, screenshots, context, and error context](runs/assets/hr07-bank-change-sixth-attempt/); `spa/src/components/layout/Sidebar.tsx`; `spa/src/components/layout/Sidebar.behavior.test.tsx`.
- **State:** This attempt created one disposable pending profile-change request. A fresh seed is required before the next run.
