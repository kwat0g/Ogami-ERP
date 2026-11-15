# HR04-APPROVE — Signal button accessible name used its description

**Date:** 2026-11-04 UTC  
**Classification:** Test locator issue (TST-HR-019)  
**Environment:** Guarded `ogami-erp-e2e` Compose project; fresh baseline with opening leave entitlements  
**Result:** Selected-actor auth setup and Employee step passed; Department Head step stopped before acting. No retry.  
**Journey run ID:** `387611b6-12a2-4c93-af73-70e6ae918e15`

## Outcome

The Employee, Department Head, and HR Officer signed in through their own sessions. The Employee submitted through Profile → Leaves; the first step passed its status, balance, attendance, approval-row, recipient, and diagnostics assertions.

The Department Head dashboard displayed the pending request without refresh. The test opened Human Resources, hovered the Leave Management link, and kept the section expanded for the bounded badge check. The screenshot shows the section still open after the check window and the Leave Management count at 1. The signal button locator still expected `Leaves: 1`, but the accessible name is built from the badge description and reads `View all Leave Management signals: 1 Leave requests awaiting your approval`. The Department Head did not act; HR was blocked; the request remained `pending_dept`.

## Evidence

- [Department Head dashboard and expanded sidebar](assets/hr04-approve-attempt-07/test-failed-3.png)
- [HR dashboard](assets/hr04-approve-attempt-07/test-failed-2.png)
- [Trace](assets/hr04-approve-attempt-07/trace.zip)
- [Journey context](assets/hr04-approve-attempt-07/journey-context.json)
- [Playwright results](assets/hr04-approve-attempt-07/process-results.json)
- Source: `Sidebar.tsx` composes the trigger's accessible name with `signal.description`; `BadgeService` defines the leave signal description as “Leave requests awaiting your approval.”

The next attempt uses that source-derived accessible name and exact count, while following the exact signal link name “Leave requests awaiting your approval: 1.”

## Coverage

The Employee step and notification audience passed. The Department Head dashboard updated live without refresh and the sidebar remained expanded; the signal dialog, queue row, and approval action remain unverified.
