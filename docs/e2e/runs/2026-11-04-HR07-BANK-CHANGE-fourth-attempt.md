# HR07-BANK-CHANGE — fourth attempt

- **Date:** 2026-11-04
- **Journey run ID:** `92f8fcb2-fb6f-4574-a25b-23085eba891b`
- **Environment:** guarded `ogami-erp-e2e` Compose stack and freshly seeded `ogami_e2e` database
- **Invocation:** `E2E_AUTH_ACTORS=employee,hr_officer,finance_officer node scripts/e2e/runner.mjs test --grep HR07-BANK-CHANGE`
- **Result:** selected-actor real-UI auth setup passed (3/3). Employee Profile and both reviewer queues opened through the UI. The read-only baseline query failed before the business submission; all remaining steps were blocked.
- **Failure:** The query assumed `profile_update_requests.hash_id` was a stored column. Source shows `HasHashId::hash_id` is an accessor derived from the integer ID, so no such database column exists.
- **Classification:** Test issue `TST-HR-003`; no product defect inferred and no transactional record created.
- **Correction:** Locate the unique process row by the employee actor, the UI-supplied request note, and the run start timestamp. Continue using the UI-returned opaque hash ID only for the UI review endpoints.
- **Evidence:** [archived trace, screenshots, context, and error context](runs/assets/hr07-bank-change-fourth-attempt/); `api/app/Common/Traits/HasHashId.php`; `spa/e2e-process/hr-profile.process.spec.ts`.
- **Next:** Re-seed the isolated database and rerun this journey alone.
