# HR07-BANK-CHANGE — eleventh attempt

- **Date:** 2026-11-04
- **Journey run ID:** `7a552498-3855-4490-ba7e-980796012460`
- **Environment:** guarded `ogami-erp-e2e` Compose stack and freshly seeded `ogami_e2e` database
- **Invocation:** `E2E_AUTH_ACTORS=employee,hr_officer,finance_officer node scripts/e2e/runner.mjs test --grep HR07-BANK-CHANGE`
- **Result:** UI sign-in passed (3/3). Employee submitted the request through Profile and HR approved it through its own review queue. The employee and HR pages showed `Awaiting Finance`; Finance then followed its live badge and found the request with the `Finance Approve` action. The action/state check stopped on an expected reviewer-name label that the Finance list did not render; the post-HR read-only verifier and final approval did not run.
- **Observed UI gap:** The expanded Finance record displayed the request note and Finance action but no `HR approved by` attribution. The frontend renders that label only when the `reviewer` relation is included; `ProfileUpdateRequestService::listForReview()` eager-loads employee/requester but not reviewer. This is a low-severity context gap because the Finance action remains available; the subsequent run will verify the HR actor in the read-only database audit and reviewer fields.
- **Correction:** Keep the UI assertions for the HR status, Finance queue hand-off, and Finance Approve action. Verify the HR actor through the read-only reviewer/audit assertions after the HR step and retain the absent UI attribution as `UX-HR-002`.
- **Evidence:** [archived screenshots, trace, error context, and journey context](runs/assets/hr07-bank-change-eleventh-attempt/); `spa/src/pages/hr/profile-update-requests/index.tsx`; `api/app/Modules/HR/Services/ProfileUpdateRequestService.php`; `api/app/Modules/HR/Resources/ProfileUpdateRequestResource.php`.
- **State:** A pending-Finance request remains in this disposable database. Reset and seed before the next attempt.
