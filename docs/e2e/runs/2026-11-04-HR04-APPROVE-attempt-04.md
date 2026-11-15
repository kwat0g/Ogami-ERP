# HR04-APPROVE — UI status label differed from the enum label

**Date:** 2026-11-04 UTC  
**Classification:** Test expectation/source-label inconsistency (TST-HR-018, UX-HR-003)  
**Environment:** Guarded `ogami-erp-e2e` Compose project; fresh baseline with opening leave entitlements  
**Result:** Selected-actor auth setup passed; the Employee submitted the request and the step failed in UI verification. Department Head and HR steps were blocked.  
**Journey run ID:** `8dec3ade-edb2-4ce3-84f8-2937c45fccc9`

## Outcome

The Employee, Department Head, and HR Officer signed in through the real login UI. The Employee navigated from the Profile menu to Leaves, opened the form, selected Vacation Leave, entered a one-day future business date and run-scoped reason, and submitted through the UI. The API response was HTTP 201. Read-only assertions passed for request `LR-202611-0001` at `pending_dept`, 1.0 day, employee/type/date/reason linkage, null approvers, unchanged leave balance, zero `on_leave` attendance rows, and the two pending workflow steps.

The test then expected “Pending department head” on the Employee detail. That page renders the `leaves/options` label “Pending dept.” A read-only PostgreSQL query confirmed `LR-202611-0001` remained `pending_dept`, 1.0 day. The failure is a mismatch between the test expectation and the UI label source, not a wrong stored status. The journey stopped before verifying the Employee's Cancel action, notification recipients, badges, or the Department Head hand-off. The Department Head and HR steps were marked blocked; no actor advanced the record.

## Evidence

- [Saved journey context](assets/hr04-approve-attempt-04/journey-context.json)
- [Playwright process results](assets/hr04-approve-attempt-04/process-results.json)
- [UI screenshot attachments](assets/hr04-approve-attempt-04/)
- [Trace](assets/hr04-approve-attempt-04/trace.zip)
- Read-only PostgreSQL result: request number `LR-202611-0001`, status `pending_dept`, 1.0 day.
- Source: `LeaveRequestController::options()` formats statuses by replacing underscores; `LeaveRequestStatus::label()` defines “Pending department head”; `LeaveRequestResource` uses the enum label.

The spec now asserts the actual Employee detail labels from the options endpoint (“Pending dept” and “Pending hr”), while retaining the HR queue's enum-derived status text and the database status assertion. The source-level label inconsistency is tracked as UX-HR-003.

## Coverage

This attempt covers the UI submission step and database assertions for initial request state. It does not cover either approval, hand-off notifications, live updates, or final balance/attendance effects. A fresh baseline is required before the next run.
