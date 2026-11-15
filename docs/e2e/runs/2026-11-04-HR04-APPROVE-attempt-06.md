# HR04-APPROVE — Temporary sidebar expansion expired before lookup

**Date:** 2026-11-04 UTC  
**Classification:** Test issue (TST-HR-019/020)  
**Environment:** Guarded `ogami-erp-e2e` Compose project; fresh baseline with opening leave entitlements  
**Result:** Selected-actor auth setup and Employee step passed; Department Head step stopped before acting. No retry.  
**Journey run ID:** `afd87d5f-4117-4f86-8d81-973e39a5d6e0`

## Outcome

The Employee, Department Head, and HR Officer signed in through their own sessions. The Employee submitted the request through Profile → Leaves. The first step passed all request status, balance, attendance, workflow row, notification audience, and Employee diagnostics assertions.

The Department Head dashboard was already open. It received the leave record in its Pending Approvals card without refresh, and the Human Resources section showed its group badge count of 1. The test expanded Human Resources and confirmed `aria-expanded=true`, but its temporary expansion expired before the badge lookup. The actual sidebar keeps a first-time section peek open for five seconds while its link group is hovered or focused. The Department Head step stopped before locating the signal or acting; HR was blocked. The UI-created request remained `pending_dept`.

## Evidence

- [Department Head screenshot showing collapsed section count and live Pending Approvals row](assets/hr04-approve-attempt-06/test-failed-2.png)
- [HR dashboard screenshot](assets/hr04-approve-attempt-06/test-failed-3.png)
- [Trace](assets/hr04-approve-attempt-06/trace.zip)
- [Journey context](assets/hr04-approve-attempt-06/journey-context.json)
- [Playwright results](assets/hr04-approve-attempt-06/process-results.json)
- Source: `Sidebar.tsx` defines a five-second temporary expansion that is retained while a contained menu link is hovered or keyboard-focused.

The next attempt hovers Leave Management after expanding the section and again before the Department Head and HR badge lookups. It retains the same exact badge count, signal destination, and approval assertions.

## Coverage

The Employee submission and its notification recipient assertion passed. A live dashboard update to the Department Head was visible without refresh. The actual sidebar signal menu and its hand-off link remain unverified; no approval transition ran.
