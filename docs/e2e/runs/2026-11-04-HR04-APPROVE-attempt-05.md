# HR04-APPROVE — Sidebar hand-off locator missed the collapsed menu

**Date:** 2026-11-04 UTC  
**Classification:** Test issue (TST-HR-019)  
**Environment:** Guarded `ogami-erp-e2e` Compose project; fresh baseline with opening leave entitlements  
**Result:** Selected-actor auth setup and Employee submission step passed; Department Head step stopped before acting. No retry.  
**Journey run ID:** `aa2f9788-4492-48fc-917e-17ac61c190b7`

## Outcome

The Employee, Department Head, and HR Officer signed in through their own sessions. The Employee submitted the Vacation Leave request through Profile → Leaves. The first journey step passed: read-only assertions confirmed `pending_dept`, 1.0 day, null approvers, unchanged balance and attendance, both pending approval rows, and the exact `leave.submitted` recipient audience.

The Department Head's dashboard was already open before submission. Without refreshing, its Pending Approvals card showed `LR-202611-0001`; the collapsed Human Resources sidebar section also showed a count of 1. The test could not find its expected “Leaves signals” button because the actual sidebar item is “Leave Management,” and its signal action is only exposed when the Human Resources section is expanded. No approval was attempted; the HR step was marked blocked. The record remained `pending_dept`.

## Evidence

- [Department Head dashboard showing the pending request and section badge](assets/hr04-approve-attempt-05/test-failed-2.png)
- [HR dashboard showing the pending leave count](assets/hr04-approve-attempt-05/test-failed-3.png)
- [Trace](assets/hr04-approve-attempt-05/trace.zip)
- [Saved journey context](assets/hr04-approve-attempt-05/journey-context.json)
- [Playwright process results](assets/hr04-approve-attempt-05/process-results.json)
- Source: `Sidebar.tsx` defines the Human Resources section, the “Leave Management” item, a “Leaves” signal label, and its actionable destination.

The next attempt expands Human Resources through the visible section button, asserts the exact sidebar signal label/count, and follows the actual “Leave requests awaiting your approval” link. This preserves the intended badge hand-off path and does not act on the record by API.

## Coverage

The Employee submission and recipient side effect passed. The Department Head dashboard received the live update without refresh, but the test did not yet navigate through the signal dialog or verify the Department Head row/actions. No approval transition or HR hand-off was exercised.
