# HR04-CANCEL-APPROVED — Employee cancels approved leave and restores effects

**Date:** 2026-11-04 UTC  
**Environment:** Guarded `ogami-erp-e2e` project; fresh baseline and 37 isolated actors  
**Result:** **2/2 Playwright tests passed** (selected-actor auth setup and one process journey); Employee, Department Head, and HR Officer authenticated through the real login UI  
**Journey duration:** 51.2 seconds; total command 66.3 seconds  
**Journey run ID:** `9817344f-9462-459b-8a30-312cb3d511d7`  
**Created record:** `LR-202611-0001`, `pending_dept → pending_hr → approved → cancelled`

## Process walkthrough

All transactional actions used the SPA and each actor's own browser session in source-defined order. The run started from a freshly migrated and seeded baseline.

1. **Employee — Profile → Leaves → New request:** submitted a one-day Vacation Leave request through the UI. It entered `pending_dept`; both approval rows were pending and no balance or attendance effect existed.
2. **Department Head — Dashboard → Human Resources → Leave Management signal → Awaiting my action:** followed the real badge/signal path and approved the request through the UI. Status advanced to `pending_hr`; the Department Head approval row recorded this actor, and HR received the actionable hand-off. The Employee's open request detail updated live.
3. **HR Officer — Dashboard → Human Resources → Leave Management signal → Awaiting my action:** followed HR's own badge and approved through the UI. Status became `approved`, one Vacation Leave day was consumed, and the requested date was marked `on_leave`.
4. **Employee — request detail → Cancel → confirmation:** confirmed cancellation through the Employee session. The API returned HTTP 200 and status became `cancelled`. The used balance returned to 0, remaining balance to 15, and the leave-created attendance row was soft-deleted. Employee and HR open details updated to Cancelled without refresh.

## Verified effects and invariants

- **Status, actors and audit:** read-only assertions confirmed the complete status path, Employee canceller and cancellation timestamp, Department Head and HR approval actors, and Employee actor on the cancellation audit entry.
- **Approval history:** both completed approvals remain recorded and current after cancellation; cancellation does not invent a further approval result.
- **Balance and attendance:** final balance is used `0.0`, remaining `15.0`. No active `on_leave` row remains. The original leave-created attendance row remains in soft-delete history with `deleted_at` set at cancellation time and its leave marker preserved.
- **Notifications:** only `leave.submitted` to eligible Department Heads, `leave.pending_hr` to eligible HR Officers, and `leave.approved` to the Employee were present. Each linked to the request. No cancellation notice was emitted, and the exact-recipient assertions passed. The queued notification check waited on persisted notification types with a bounded condition-based poll.
- **Badges, live updates, and email:** Employee and HR open details updated without refresh; HR and Department Head actionable leave badge counts returned to zero. Mailpit IDs for intended recipients did not change. Diagnostics passed for all three role pages.
- **Documents and finance:** this process creates no PDF/export or journal; approved leave affects attendance and leave balance only, and cancellation restored those effects.

## Annotated screenshots

- [Employee files the request](assets/hr04-cancel-approved-full-run/walkthrough/001-employee-files-a-vacation-leave-request.png)
- [Department Head follows the badge and approves](assets/hr04-cancel-approved-full-run/walkthrough/002-department-head-follows-the-badge-and-approves.png)
- [HR follows its badge and approves](assets/hr04-cancel-approved-full-run/walkthrough/003-hr-follows-the-badge-and-approves.png)
- [Employee cancels approved leave](assets/hr04-cancel-approved-full-run/walkthrough/004-employee-cancels-while-approved.png)
- [Journey context](assets/hr04-cancel-approved-full-run/journey-context.json)
- [Playwright process results](assets/hr04-cancel-approved-full-run/process-results.json)

## Process as observed

```mermaid
sequenceDiagram
    actor EMP as Employee
    actor DH as Department Head
    actor HR as HR Officer
    participant UI as OGAMI ERP UI
    participant DB as Leave workflow and attendance
    EMP->>UI: Profile → Leaves: submit one-day VL
    UI->>DB: Create request and pending DH/HR approval rows
    DB-->>DH: leave.submitted to eligible Department Heads
    DH->>UI: Follow badge and approve
    UI->>DB: pending_dept → pending_hr
    DB-->>HR: leave.pending_hr to eligible HR Officers
    HR->>UI: Follow badge and approve
    UI->>DB: pending_hr → approved; consume one day and mark on_leave
    DB-->>EMP: leave.approved
    EMP->>UI: Cancel from own request detail
    UI->>DB: Cancel approved request
    DB-->>DB: approved → cancelled; restore balance and soft-delete leave attendance
    DB-->>EMP: Employee detail updates live
    DB-->>HR: HR detail updates live; actionable badge clears
```

## Coverage and remaining work

This completes `HR04-CANCEL-APPROVED`. Three E2E test issues were corrected with evidence-based, condition-preserving changes and this clean-baseline run passed: the active-attendance query now excludes soft-deleted rows, queued notice assertions wait on persisted expected types, and the Leaves page heading uses a bounded timeout above the measured cold bundle latency. See attempts [01](2026-11-04-HR04-CANCEL-APPROVED-attempt-01.md), [02](2026-11-04-HR04-CANCEL-APPROVED-attempt-02.md), and [03](2026-11-04-HR04-CANCEL-APPROVED-attempt-03.md). No application code changed.

`HR04-CANCEL-PENDING-HR` also passed. `HR04-CANCEL-PENDING-DEPT` remains blocked by application finding [FND-HR-001](../FINDINGS.md#fnd-hr-001--leave-cancellation-500s-when-retiring-pending-approval-records); its transaction rolls back. Insufficient balance, self-approval, wrong-department authorization, invalid-transition API checks, and payroll consumption remain planned.
