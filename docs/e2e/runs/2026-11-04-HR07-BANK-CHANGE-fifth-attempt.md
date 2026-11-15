# HR07-BANK-CHANGE — fifth attempt

- **Date:** 2026-11-04
- **Journey run ID:** `82a987d4-a4a6-4fd6-ab6d-bdfaa8331517`
- **Environment:** guarded `ogami-erp-e2e` Compose stack and freshly seeded `ogami_e2e` database
- **Invocation:** `E2E_AUTH_ACTORS=employee,hr_officer,finance_officer node scripts/e2e/runner.mjs test --grep HR07-BANK-CHANGE`
- **Result:** selected-actor UI sign-in passed (3/3); employee/profile/reviewer queues opened through the UI; employee submitted a unique bank-change request. HR's open queue showed the new `Pending HR` row and its sidebar badge showed count `1`. The test stopped while opening the badge handoff; HR approval and Finance review were blocked.
- **Failure:** The test assumed the badge trigger's accessible name would use the visible label “Awaiting my action.” The source provides a distinct descriptive accessible name (“Profile changes you can review”) while the badge-menu link displays “Awaiting my action.” The request submit and live list/badge update were observed, but the database/audit assertions were not reached.
- **Classification:** Test issue `TST-HR-004`; no product defect inferred.
- **Correction:** Locate the badge by role, route label, and count; then select the visible “Awaiting my action” link inside its dialog.
- **Evidence:** [archived trace, screenshots, context, and error context](runs/assets/hr07-bank-change-fifth-attempt/); `spa/src/components/layout/Sidebar.tsx`; `spa/e2e-process/hr-profile.process.spec.ts`.
- **State:** This attempt created one disposable pending profile-change request in `ogami_e2e`. The next run must start after a fresh E2E seed.
