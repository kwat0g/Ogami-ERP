# HR04-APPROVE — Post-approval assertion expected the row to remain in the acting inbox

**Date:** 2026-11-04 UTC  
**Classification:** Test expectation issue (TST-HR-021)  
**Environment:** Guarded `ogami-erp-e2e` Compose project; fresh seeded baseline with opening leave entitlements  
**Result:** Selected-actor auth setup passed; Employee step passed; Department Head acted through the UI; the step stopped at its post-action queue assertion. HR was blocked. No retry.  
**Journey run ID:** `e1790417-532e-4479-8915-3f3a8ec69a81`

## Outcome

The Employee, Department Head, and HR Officer signed in through their own sessions. Employee filed the request through Profile → Leaves and passed the status, balance, attendance, approval-row, recipient, badge, and diagnostics assertions. Department Head found the work through the live dashboard/sidebar hand-off, opened the signal dialog, followed “Awaiting my action,” and saw the request's “Pending dept” status with enabled Approve and Reject actions.

Department Head approved through the confirmation dialog. The immediately following assertion expected the same row in the same `awaiting_my_approval` queue with a new “Pending hr” label. That queue belongs to the acting Department Head; after the action, the row is no longer theirs to approve. The assertion failed while the UI was reconciling the queue. The failure screenshot then showed the Department Head queue at 0 requests, and the Employee detail showed “Pending hr” with Department Head approved and HR pending.

Read-only database verification confirmed request `LR-202611-0001` at `pending_hr`, Department Head `e2e+department_head@ogami-e2e.test` recorded, HR approver null, and approval rows `department_head: approved` / `hr_officer: pending`. The notification hand-off and HR action were not verified in this attempt because the process runner correctly blocked later steps after the Department Head step failed.

## Evidence

- [Saved journey context](assets/hr04-approve-attempt-09/journey-context.json)
- [Playwright process results](assets/hr04-approve-attempt-09/process-results.json)
- [Failure screenshots](assets/hr04-approve-attempt-09/)
- [Trace](assets/hr04-approve-attempt-09/trace.zip)
- [Error context](assets/hr04-approve-attempt-09/error-context.md)
- Read-only DB result: `pending_hr`; Department Head approver set; HR approval step pending.
- Source: `LeaveApprovalPolicy::awaitingMyApprovalQuery()` selects only the current actor's pending workflow stage; `LeaveRequestService::approveDept()` advances the request; the SPA filters `awaiting_my_approval` rows after mutation.

The test now expects the request row to leave the Department Head's actionable queue and continues to assert `pending_hr` through read-only state, the Employee's live detail, the HR badge/queue, and approval attribution. No application code changed.

## Environment note

After the browser failure, an ad hoc `docker compose` diagnostic omitted `-p ogami-erp-e2e` and resolved the default `ogami-erp` Compose project. It read only the service listing/log tail; three attempted database connections failed before any query returned data. No database write or business mutation occurred. Direct Compose diagnostics now include the explicit E2E project name; journey execution itself used the guarded runner. This isolation mistake is logged as ENV-E2E-005.

## Coverage

The Employee submission and the Department Head UI approval action completed. The stored transition `pending_dept → pending_hr`, actor attribution, and Employee live status were verified read-only/UI. The HR notification recipient set, HR queue hand-off, final approval, leave-balance consumption, `on_leave` attendance, final audit/notifications, and final badges remain unverified.
