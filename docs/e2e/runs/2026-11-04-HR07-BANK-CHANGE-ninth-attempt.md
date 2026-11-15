# HR07-BANK-CHANGE — ninth attempt

- **Date:** 2026-11-04
- **Journey run ID:** `63c267d2-3d5e-4af4-b6b5-164b483a973f`
- **Environment:** guarded `ogami-erp-e2e` Compose stack and freshly seeded `ogami_e2e` database
- **Invocation:** `E2E_AUTH_ACTORS=employee,hr_officer,finance_officer node scripts/e2e/runner.mjs test --grep HR07-BANK-CHANGE`
- **Result:** UI sign-in passed (3/3). The employee, HR, and Finance opened their own profile/queue screens. The employee submitted a bank change through Profile; the UI showed Pending HR, the live HR queue received the request row, and the HR badge count updated to 1. Read-only database checks confirmed a `pending` request, unchanged employee bank fields, no reviewer, and zero notifications. Step 2 then failed in its badge invariant; HR approval and Finance approval were blocked.
- **Failure:** The invariant expected “Awaiting my action” in the badge button's accessible name. The rendered button label is “View all Profile Change Requests signals: 1 Profile changes you can review”; “Awaiting my action” is the badge popover link text. The error snapshot confirms the row, count, HR Approve action, and correct trigger description were present. This is a test assertion issue, not a product finding.
- **Correction:** Match the trigger's actual accessible description in the invariant. The separate hand-off helper still follows the visible “Awaiting my action” link and confirms the resulting route and queue row.
- **Evidence:** [archived screenshots, trace, error context, and journey context](runs/assets/hr07-bank-change-ninth-attempt/); `spa/src/components/layout/Sidebar.tsx`; `spa/e2e-process/hr-profile.process.spec.ts`.
- **State:** One disposable pending request remains from this attempt. Reset and seed the isolated baseline before the next run.
