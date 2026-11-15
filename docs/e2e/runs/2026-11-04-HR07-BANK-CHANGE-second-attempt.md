# HR07-BANK-CHANGE — second attempt

- **Date:** 2026-11-04
- **Journey run ID:** `b74999ec-23ac-4856-b522-9d6eeaff1948`
- **Environment:** guarded `ogami-erp-e2e` Compose stack and freshly seeded `ogami_e2e` database
- **Invocation:** `E2E_AUTH_ACTORS=employee,hr_officer,finance_officer node scripts/e2e/runner.mjs test --grep HR07-BANK-CHANGE`
- **Result:** selected-actor real-UI auth setup passed (3/3); the journey again failed before creating a profile-change request. The authentication redirect to `/dashboard` worked.
- **Failure:** The test expected a dashboard button named “My profile.” The authenticated employee dashboard instead exposes `Profile menu for E2E employee`; opening it reveals the `Profile` menu item linking to `/self-service/profile`.
- **Classification:** Test issue `TST-HR-001`; no product defect inferred and no transactional data created.
- **Correction:** Open the visible topbar profile menu and select its `Profile` menu item. HR and Finance still reach their queues through their own sidebar sections.
- **Evidence:** [archived trace, screenshots, context, and error context](runs/assets/hr07-bank-change-second-attempt/); `spa/src/components/layout/ProfileDropdown.tsx`; `spa/e2e-process/hr-profile.process.spec.ts`.
- **Next:** Re-seed the isolated database and rerun this journey alone.
