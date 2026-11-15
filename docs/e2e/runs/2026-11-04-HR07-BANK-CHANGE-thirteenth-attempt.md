# HR07-BANK-CHANGE — thirteenth attempt

- **Date:** 2026-11-04
- **Journey run ID:** `2e20e01c-0b79-43e4-a99d-cf95cbdcabf9`
- **Environment:** guarded `ogami-erp-e2e` Compose stack and freshly seeded `ogami_e2e` database
- **Invocation:** `E2E_AUTH_ACTORS=employee,hr_officer,finance_officer node scripts/e2e/runner.mjs test --grep HR07-BANK-CHANGE`
- **Result:** UI sign-in passed (3/3). Employee submitted the bank change; HR approved; Finance found and approved its leg through its own queue. Employee saw Approved and the updated masked bank account. The assertion then incorrectly expected the approved item to disappear from the HR and Finance lists, so final read-only verification and the remaining invariant checks did not run.
- **Failure:** Both list pages start with status “All statuses”; the backend supports listing approved requests, so a completed record can remain visible without its approval action. No product defect inferred.
- **Correction:** Assert Approved in each reviewer row and assert each role's approval button is absent. Keep the sidebar actionable badge count checks, which should fall to zero when no work awaits that actor.
- **Evidence:** [archived screenshots, trace, error context, and journey context](runs/assets/hr07-bank-change-thirteenth-attempt/); `spa/src/pages/hr/profile-update-requests/index.tsx` initializes `status` to `all` and maps the updated result into the active query; `api/app/Modules/HR/Services/ProfileUpdateRequestService.php` allows Finance to list approved bank-change records.
- **State:** The UI approval chain appears complete, but final DB/audit/mail verification was blocked by the test assertion. Seed a fresh baseline before rerunning.
