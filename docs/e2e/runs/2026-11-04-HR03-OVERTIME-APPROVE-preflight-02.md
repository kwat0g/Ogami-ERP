# HR03-OVERTIME-APPROVE — preflight attempt 02

**Result:** The guarded Playwright runner stopped during spec compilation again, before browser setup. No actor signed in and no business record was created.

- **Journey:** `HR03-OVERTIME-APPROVE`
- **Selected actors:** Employee and Department Head
- **Invocation:** `E2E_AUTH_ACTORS=employee,department_head node scripts/e2e/runner.mjs test --grep HR03-OVERTIME-APPROVE`
- **Observed:** The first TST-HR-036 edit removed the shared `assertAuditActor` import even though the earlier `HR03-PUNCH` steps still use it. Playwright reported two unresolved references in the existing punch assertions. This runner compile occurred before auth setup; the app journey did not start.
- **Correction:** Restore the shared import while keeping the overtime nullable-email comparisons as direct equality assertions.
- **Classification:** Test issue TST-HR-037; no product behavior was exercised.
- **Evidence:** Guarded runner output; `spa/e2e-process/hr-attendance.process.spec.ts:611,661`.
- **Status:** Corrected in E2E test code. The next invocation is a fresh-baseline journey attempt.
