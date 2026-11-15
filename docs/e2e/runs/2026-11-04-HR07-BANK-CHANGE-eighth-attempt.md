# HR07-BANK-CHANGE — eighth attempt

- **Date:** 2026-11-04
- **Journey run ID:** `5a7ab9a0-24cf-4992-a421-13ac30636596`
- **Environment:** guarded `ogami-erp-e2e` Compose stack and freshly seeded `ogami_e2e` database
- **Invocation:** `E2E_AUTH_ACTORS=employee,hr_officer,finance_officer node scripts/e2e/runner.mjs test --grep HR07-BANK-CHANGE`
- **Result:** UI sign-in passed (3/3); all three users reached their process screens; Employee submitted the request; HR found it through its live queue/badge and expanded it. The assertion on requested bank name stopped before HR approval; the remaining process steps were blocked.
- **Failure:** The bank name appeared twice in responsive DataTable markup (desktop and mobile variants), so a page-wide exact text locator was ambiguous. No product defect inferred.
- **Correction:** Scope the assertion to the visible “Requested changes” region.
- **Evidence:** [archived trace, screenshots, context, and error context](runs/assets/hr07-bank-change-eighth-attempt/); `spa/src/pages/hr/profile-update-requests/index.tsx`; `spa/e2e-process/hr-profile.process.spec.ts`.
- **State:** One disposable pending request remains from this attempt; reseed before the next run.
