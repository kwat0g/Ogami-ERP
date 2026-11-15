# HR04-APPROVE — Department Head queue label assertion used the enum text

**Date:** 2026-11-04 UTC  
**Classification:** Test expectation/source-label issue (TST-HR-018)  
**Environment:** Guarded `ogami-erp-e2e` Compose project; fresh seeded baseline with opening leave entitlements  
**Result:** Selected-actor auth setup passed; Employee step passed; Department Head step stopped before approval. No retry.  
**Journey run ID:** `5ec44a1a-8f67-4532-b208-a4fb5b4acc2d`

## Outcome

Employee, Department Head, and HR Officer signed in through their own sessions. The Employee created the Vacation Leave request through Profile → Leaves. The first step passed its stored status, leave balance, attendance, approval rows, notification recipients, sidebar badge, and page diagnostics assertions.

The Department Head dashboard showed the request without a refresh. The actor expanded Human Resources, followed the Leave Management signal button, opened the signal dialog, and followed “Leave requests awaiting your approval: 1” to the queue. The unique request row appeared with the Department Head's Approve and Reject actions. Its visible label was “Pending dept,” while the test still expected the enum's “Pending department head.” The assertion stopped the step before the Department Head clicked either action. The request remained `pending_dept`; the HR approval step was blocked and performed no action.

## Evidence

- [Saved journey context](assets/hr04-approve-attempt-08/journey-context.json)
- [Playwright process results](assets/hr04-approve-attempt-08/process-results.json)
- [Failure screenshots](assets/hr04-approve-attempt-08/)
- [Trace](assets/hr04-approve-attempt-08/trace.zip)
- [Error context](assets/hr04-approve-attempt-08/error-context.md)
- Source: `spa/src/pages/leaves/index.tsx` maps row status through `/leaves/options`; `LeaveRequestController::options()` formats `pending_dept` as “Pending dept”; the enum has the longer `LeaveRequestStatus::label()`.

The test expectation now follows the rendered queue labels (“Pending dept” and “Pending hr”) while keeping the exact stored-status and approval-actor assertions. UX-HR-003 tracks the label inconsistency. A new fresh-baseline run is required to verify the correction.

## Coverage

The Employee submission and Department Head's real badge-to-queue hand-off passed. No approval action was taken in this attempt. The Department Head approval, HR hand-off, HR approval, balance consumption, attendance effect, and final cross-role state remain unverified.
