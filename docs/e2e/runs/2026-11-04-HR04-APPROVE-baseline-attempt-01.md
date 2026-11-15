# HR04-APPROVE — Baseline precondition failure

**Date:** 2026-11-04 UTC  
**Classification:** Test/baseline issue (resolved in E2E-only seeder)  
**Environment:** Guarded `ogami-erp-e2e` Compose project; freshly migrated `ogami_e2e` database  
**Result:** Selected-actor UI auth setup passed; the journey failed before step 1. No retry was used.  
**Journey run ID:** None; the baseline precondition failed before journey context creation.

## Outcome

The E2E Employee, Department Head, and HR Officer each signed in through the login UI in their own role session. The journey's read-only precondition query found the seeded Employee and one active Vacation Leave type, but no current-year `employee_leave_balances` row. The explicit assertion for opening entitlement failed before any request UI was opened. No leave request, approval, notification, audit row, email, or attendance record was created.

The first container TypeScript preflight found two unused declarations in the new journey spec. They were removed without changing any process assertion; the next preflight passed before this browser run.

## Evidence

- [Failure screenshot](assets/hr04-approve-baseline-red/test-failed-1.png)
- [Playwright trace](assets/hr04-approve-baseline-red/trace.zip)
- [Error context](assets/hr04-approve-baseline-red/error-context.md)
- [Playwright process results](assets/hr04-approve-baseline-red/process-results.json)

The assertion failure is `E2E baseline should include opening Vacation Leave entitlement`: the read-only query returned a null balance ID. This is an E2E fixture omission, not evidence of a leave-process UI defect. `E2EAccountSeeder` now supplies opening entitlements for the seeded `employee` actor from active leave types' `default_balance` for the current Asia/Manila year. Those rows are baseline master data; the process request and both approvals remain UI-created/UI-performed. The seeder change is gated by `E2EEnvironment::assertEnabled()` and is documented in [the platform baseline](../02-platform.md#environment-guard-and-baseline).

## Coverage

No business step or state transition was exercised. The UI journey remains pending a new fresh-baseline run with the corrected E2E reference data.
