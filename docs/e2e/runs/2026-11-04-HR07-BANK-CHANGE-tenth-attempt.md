# HR07-BANK-CHANGE — tenth attempt

- **Date:** 2026-11-04
- **Journey run ID:** `111e183b-0958-40fc-ac0e-9df9c4735ce3`
- **Environment:** guarded `ogami-erp-e2e` Compose stack and freshly seeded `ogami_e2e` database
- **Invocation:** `E2E_AUTH_ACTORS=employee,hr_officer,finance_officer node scripts/e2e/runner.mjs test --grep HR07-BANK-CHANGE`
- **Result:** UI sign-in passed (3/3). Employee submitted the bank change through their Profile session; the pending state and HR queue/badge hand-off passed with read-only DB checks. HR found and reviewed the request through its own queue, then submitted first-stage approval through the UI; the API response was HTTP 200 and the page updated. The step stopped on a duplicate status text assertion; read-only post-HR status verification, Finance hand-off, and final approval did not run.
- **Failure:** The test searched page-wide for “Awaiting Finance.” HR's status filter option and two responsive status chips all matched. The request itself had reached the expected state, but the assertion did not isolate its row. No product defect inferred.
- **Correction:** Scope the HR status to the employee's table row and the employee view status to that request's list item. The Finance badge invariant now uses the rendered “Profile changes you can review” trigger description, while the actual “Awaiting my action” popover link remains the hand-off target.
- **Evidence:** [archived screenshots, trace, error context, and journey context](runs/assets/hr07-bank-change-tenth-attempt/); `spa/src/pages/hr/profile-update-requests/index.tsx`; `spa/src/pages/self-service/profile.tsx`; `api/app/Modules/Dashboard/Services/BadgeService.php`.
- **State:** A pending-Finance request remains in this disposable database. Reset and seed before the next attempt.
