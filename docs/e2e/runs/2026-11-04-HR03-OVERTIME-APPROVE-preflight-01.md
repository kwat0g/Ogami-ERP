# HR03-OVERTIME-APPROVE — preflight attempt 01

**Result:** The guarded Playwright runner stopped during TypeScript compilation. No browser context opened, no actor signed in, and no overtime request was created or advanced.

- **Journey:** `HR03-OVERTIME-APPROVE`
- **Selected actors:** Employee and Department Head
- **Invocation:** `E2E_AUTH_ACTORS=employee,department_head node scripts/e2e/runner.mjs test --grep HR03-OVERTIME-APPROVE`
- **Observed:** Runner TypeScript compilation rejected passing `OvertimeAuditRow.actor_email` (`string | null`) to `assertAuditActor`, whose input only permits an optional string. The process journey did not start.
- **Classification:** Test issue TST-HR-036; no product behavior was exercised.
- **Correction:** Compare the nullable read-only audit actor value directly with the expected actor email, so a missing actor still fails the assertion.
- **Evidence:** The guarded runner output; `spa/e2e-process/hr-attendance.process.spec.ts`.
- **Status:** Corrected in E2E test code. A fresh-baseline run is required before the journey result can be reported.
