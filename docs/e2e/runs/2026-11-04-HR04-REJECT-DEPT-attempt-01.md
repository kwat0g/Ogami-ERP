# HR04-REJECT-DEPT — Department Head badge lookup did not reopen the sidebar section

**Date:** 2026-11-04 UTC  
**Classification:** Test navigation issue (TST-HR-022)  
**Environment:** Guarded `ogami-erp-e2e` project; fresh seeded baseline with opening leave entitlements  
**Result:** Selected-role auth setup passed; Employee step passed; Department Head rejection step stopped before action. No retry.  
**Journey run ID:** `181178e8-24fb-45b3-9f3e-81cc2e385119`

## Outcome

Employee, Department Head, and HR Officer signed in through their own sessions. The Employee submitted a one-day Vacation Leave request through Profile → Leaves. The Employee step passed its stored `pending_dept` status, approval rows, unchanged leave balance and attendance, and exact `leave.submitted` recipient assertion.

The Department Head dashboard showed `LR-202611-0001` in Pending Approvals and the collapsed Human Resources section showed a badge count of 1. The test looked for the signal button before reopening Human Resources, so it timed out with no matching accessible button. It did not follow the badge or open the queue, and it did not reject the request. The stored request remained `pending_dept`; the journey stopped before the UI action.

## Evidence

- [Saved journey context](assets/hr04-reject-dept-attempt-01/journey-context.json)
- [Playwright process results](assets/hr04-reject-dept-attempt-01/process-results.json)
- [Failure screenshots](assets/hr04-reject-dept-attempt-01/)
- [Trace](assets/hr04-reject-dept-attempt-01/trace.zip)
- [Employee walkthrough screenshot](assets/hr04-reject-dept-attempt-01/walkthrough/001-employee-files-a-vacation-leave-request.png)
- Screenshot 3 shows the live Department Head dashboard row and collapsed Human Resources badge count 1.

The test now reopens Human Resources and hovers Leave Management before matching the exact signal button, as in the completed approval journey. No application code changed.

## Coverage

Employee creation and the initial recipient audience passed. Department Head rejection, rejection reason, Employee rejection notification, approval-row skip, balance/attendance invariants after rejection, and final badges remain unverified pending a clean-baseline rerun.
